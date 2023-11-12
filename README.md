# wordpress_openai
This code can be copied and pasted into a code snippet with the the WPCode Wordpress plugin (to appear with a shortcode).  See the results of this code at https://onebread.co.uk

Pre-requisites:
- You will need an OpenAI API key (see https://openai.com/blog/openai-api for details) - replace "<YOUR_API_KEY>" with this value in lines 21 and 67 of the code

The end to end execution of this code costs around $0.11 at the point of writing (previously $0.15 when using the completions endpoint with davinci-003

When a user enters a bible passage and clicks on submit, the following happens:

1. Checks whether there's a post for this bible passage already, if not...
2. Calls off to gpt-3.5-turbo-1106 completions API to get a vivid description of the bible passage in 20 words (temp 1)
3. Passes this description to dall.e API (version 2) to get an image back as b64 file
4. Uploads the image to the Wordpress media library and adds alt text
5. Calls off to gpt-3.5-turbo-1106 completions API to get the bible passage summarised as a limerick (temp 0.5)
6. Calls off to gpt-3.5-turbo-1106 completions API to get some practical application point suggestions (temp 0.7)
7. Calls off to gpt-3.5-turbo-1106 completions API to get a list of related passages (temp 0.5)
8. Writes all of these to a Wordpress post with the category set to be the book of the bible
