<?php

$API_KEY = 'your_api_key_here';

// Function to call chatGPT endpoint with prompt 
// Returns response, time taken and number of tokens for request and response
if(!function_exists("callchatgpt")) {
	function callchatgpt($prompt,$temp) {
		// Set the API endpoint and parameters
		$url = 'https://api.openai.com/v1/completions';
		$parameters = array(
			'model' => 'text-davinci-003',
			'prompt' => $prompt,
			'max_tokens' => 500, // each token is about 3/4 of a word and costs around $0.02 per thousand tokens
			'temperature' => $temp, // 0 = most deterministic, 1 is most creative
			'presence_penalty' => 1 // -2 = encourage repetition, 2 is avoid repetition
		);

		// Set the API key in the header of the request
		$headers = array(
			'Content-Type: application/json',
			'Authorization: Bearer ' . $API_KEY
		);

		// Send the request to the API
		$start_time = microtime(true);
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($parameters));
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		$response = curl_exec($ch);
		$end_time = microtime(true);

		print_r($response);

		// Display the response and performance metrics
		$duration = $end_time - $start_time;

		$chatgpt_output = [
			"response" => json_decode($response, true)['choices'][0]['text'],
			"time" => $duration,
			"request_token" => json_decode($response, true)['usage']['prompt_tokens'],
			"response_token" => json_decode($response, true)['usage']['completion_tokens']
		];

		return $chatgpt_output;
	}
}

// Function to call Dall.e endpoint with prompt 
// Returns response, time taken and number of tokens for request and response
if(!function_exists("calldalle")) {
	function calldalle($prompt) {
		// Set the API endpoint and parameters
		$url = 'https://api.openai.com/v1/images/generations';
		$parameters = array(
			'prompt' => $prompt,
			'size' => '1024x1024',
			'n' => 1,
			'response_format' => 'b64_json'
		);

		// Set the API key
		$headers = array(
			'Content-Type: application/json',
			'Authorization: Bearer ' . $API_KEY
		);

		// Send the request to the API
		$start_time = microtime(true);
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($parameters));
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		$response = curl_exec($ch);
		$end_time = microtime(true);

		// print_r($response);

		// Display the response and performance metrics
		$duration = $end_time - $start_time;

		$dalle_output = [
			"response" => json_decode($response, true)['data'][0]['b64_json'],
			"time" => $duration,
		];

		return $dalle_output;
	}
}

// Check whether the user has write access - if not, print out error message
if (current_user_can('edit_posts')) {

	include(ABSPATH . "wp-admin/includes/admin.php");

	// Check if form was submitted
	if(isset($_POST['submit'])){
		// Retrieve the value of the text box
		$textbox_value = $_POST['textbox'];
	
		// Display the value of the text box
		echo "The value of the text box is: " . $textbox_value;
		
		$reading = $textbox_value;
	
		// User has write access, so check if the reading already exists
		echo "Examining reading: " . $reading . "<br>";
		echo "Checking whether post exists for " . $reading . "<br>";

		// Try to get the post that is the exact reading, and grab the ID
		$found_post = get_page_by_title($reading, OBJECT, "post");
		//echo "Post title is " . $found_post . "<br/>";

		// It doesn't exist... so we want to generate it
		if (! is_object($found_post)) {

			echo "Didn't find a post... creating a new one<br>";

			// Create a post with the following attributes
			// Title is the reading
			// Category is the book of the bible
			// Body of the post is:
			// - Read the passage at Bible gateway
			// - Summary of the passage in limerick form
			// - Application points from the passage

			// Title
			$post_title = $reading;

			// Check whether the book of the bible exists as a category or not

			// Extract the book name (all of the words and any leading numbers from the reading)
			preg_match_all('/((?:[1-9]\s)?[^0-9]{1,40})[0-9]/', $reading, $match);
			$reading_book = $match[1][0];
			echo "Book is " . $reading_book . " - adding to categories if not already there<br>";

			// check whether the category exists and if it doesn't, create it
			$category_id = term_exists( $reading_book, 'category' ); //$tax = ‘category’
			if ( !$category_id ) {
				$category_id = wp_insert_term( $reading_book, 'category',array('parent'=>$parent,'slug'=>$slug) );
			}
			echo "Category ID is " . $category_id['term_id'] . "<br>";

			$post_content = "";

			// Replace the parameter placeholder with the current value
			$link = str_replace("parameter", urlencode($reading), "https://www.biblegateway.com/passage/?search=parameter&version=NIV");

			$link_text = '<p><a href="' . $link . '">Read the passage at biblegateway.com</a></p>';
			echo "$link_text";

			$post_content .= $link_text;
			$post_content .= "<p>All content after this point has been generated by a computer. ";

			// Create the visualisation
			$visualisation_prompt = 'Describe bible passage ' . $reading . ' as a vivid image in 20 words';

			echo "Visualisation prompt is: " . $visualisation_prompt;

			// call off to chatgpt api
			$visualisation_response = callchatgpt($visualisation_prompt,1);

			// call off to dall.e with the visualisation response
			$dalle_prompt = 'An expressive oil painting of ' . $visualisation_response['response'];
			$dalle_prompt = str_replace("\n", "", $dalle_prompt);

			echo "Dalle prompt is: " . $dalle_prompt;

			$dalle_response = calldalle($dalle_prompt);

			// Set image URL and alt text
			$base64_img = $dalle_response['response'];
			$alt_text = "Image generated from Dall.e prompt '" . $dalle_prompt . "' in " . $dalle_response['time'] . " seconds,";
			$alt_text .= " which in turn was generated by the text-davinci-003 model from OpenAI";

			$upload_dir  = wp_upload_dir();
			$upload_path = str_replace( '/', DIRECTORY_SEPARATOR, $upload_dir['path'] ) . DIRECTORY_SEPARATOR;

			$img = str_replace( 'data:image/png;base64,', '', $base64_img );
			$img = str_replace( ' ', '+', $img );
			$decoded = base64_decode( $img );
			$filename = 'dalleimage' . '.png';
			$file_type = 'image/png';
			$hashed_filename = md5( $filename . microtime() ) . '_' . $filename;

			// Save the image in the uploads directory.
			$upload_file = file_put_contents( $upload_path . $hashed_filename, $decoded );

			$attachment = array(
				'post_mime_type' => $file_type,
				'post_title'     => preg_replace( '/\.[^.]+$/', '', basename( $hashed_filename ) ),
				'post_content'   => '',
				'post_status'    => 'inherit',
				'guid'           => $upload_dir['url'] . '/' . basename( $hashed_filename )
			);

			$attachment_id = wp_insert_attachment( $attachment, $upload_dir['path'] . '/' . $hashed_filename );

			print_r($attachment_id);

			$image_text = '<!-- wp:image {"id":' . $attachment_id . '} --><figure class="wp-block-image"><img src="'; 
			$image_text .= wp_get_attachment_url($attachment_id) . '" alt="' . $alt_text . '"/></figure><!-- /wp:image -->';

			$post_content .= $image_text;
			$post_content .= "<p>" . $alt_text . "</p>";

			// Create the limerick
			$limerick_prompt = 'Write a limerick of bible passage ' . $reading . ' without mentioning ' . $reading;

			echo "Limerick prompt is: " . $limerick_prompt;

			// call off to chatgpt api
			$limerick_response = callchatgpt($limerick_prompt,0.5);

			$limerick_text = "<h3>Limerick</h3>";
			$limerick_text .= "<p>" . str_replace("\n","<br>",$limerick_response['response']) . "</p>";
			$limerick_text .= "<p><em>Generated in " . $limerick_response['time'] . " seconds ";
			$limerick_text .= "using " . $limerick_response['request_token'] . " request tokens ";
			$limerick_text .= "and " . $limerick_response['response_token'] . " response tokens ";
			$limerick_text .= "and the text-davinci-003 model from OpenAI with a temperature of 0.5.";
			$limerick_text .= "</em></p>";
			// TODO: Add in the monetary cost of this

			echo $limerick_text;
			$post_content .= $limerick_text;

			// Create the action points
			$action_prompt = "Provide one practical way that I can respond to the teaching of bible passage " . $reading . " in less than 200 words";

			echo "Action prompt is: " . $action_prompt;
			// call off to chatgpt api
			$action_response = callchatgpt($action_prompt,0.7);

			$action_text = "<h3>Potential actions to take in response</h3>";
			$action_text .= "<p>" . str_replace("\n","<br>",$action_response['response']) . "</p>";
			$action_text .= "<p><em>Generated in " . $action_response['time'] . " seconds ";
			$action_text .= "using " . $action_response['request_token'] . " request tokens ";
			$action_text .= "and " . $action_response['response_token'] . " response tokens ";
			$action_text .= "and the text-davinci-003 model from OpenAI with a temperature of 0.7. ";
			$action_text .= "</em></p>";
			// TODO: Add in the monetary cost of this

			echo $action_text;
			$post_content .= $action_text;

			// Create the related passages prompt
			$passages_prompt = "Summarise the themes of " . $reading . " in 20 words, then give three related bible passages, explaining for each passage in 20 words why it is related";

			echo "Passage prompt is: " . $passages_prompt;
			// call off to chatgpt api
			$passages_response = callchatgpt($passages_prompt,0.5);

			$passages_text = "<h3>Related passages</h3>";
			$passages_text .= "<p>" . str_replace("\n","<br>",$passages_response['response']) . "</p>";
			$passages_text .= "<p><em>Generated in " . $passages_response['time'] . " seconds ";
			$passages_text .= "using " . $passages_response['request_token'] . " request tokens ";
			$passages_text .= "and " . $passages_response['response_token'] . " response tokens ";
			$passages_text .= "and the text-davinci-003 model from OpenAI with a temperature of 0.5. ";
			$passages_text .= "</em></p>";
			// TODO: Add in the monetary cost of this

			echo $passages_text;
			$post_content .= $passages_text;

			// Create the post object
			$new_post = array(
				'post_title'    => $post_title,
				'post_content'  => $post_content,
				'post_status'   => 'publish',
				'post_author'   => 1,
				'post_category' => array($category_id['term_id'])
			);

			// Insert the post into the database
			$post_id = wp_insert_post($new_post);

			// Display success or error message
			if ($post_id) {
				echo 'Post created successfully with ID: ' . $post_id;
			} else {
				echo 'Error creating post.';
			}

		} else {
			// post exists, output the link to the post
			echo "A post already exists with the title " . $reading . ".  ";

			$found_post_id = $found_post->ID;

			echo '<a href="' . get_permalink($found_post) . '">View it</a><br><br>' ;
		}
	} else {
		echo '<form method="post">';
		echo '<label for="textbox">Enter a bible passage:</label>';
		echo '<input type="text" id="textbox" name="textbox"><br><br>';
		echo '<button type="submit" name="submit">Submit</button>';
		echo '</form>';
	}
} else {
	// User does not have write access, show error message or redirect
    echo "<p>Sorry, you do not have permission to generate summaries of new passages</p>";
}
?>
