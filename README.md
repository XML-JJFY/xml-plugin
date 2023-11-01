# XML Plugin

### BLS Internship

A plugin that parses the company provided url gathering the job information inserting it into a table that has all the information.

The plugin will run once a day on the first visit to the site. If there is a large amount of new links, or the CRONS job is not working. There is a button in the settings to run the plugin.

Included:

1. The zipped code for the plugin. This will be used to upload the plugin to the site.
2. Start code for adding pictures via code.

To Use:

1. Upload the zipped plugin to the site
2. Activate the plugin.
3. Go to settings for the plugin and select your Fomrinator Form ID.
4. Have users link their XML, layout of the XML and other information needed is on the frontend page.

Future Improvements:

1. Adding a custom post to eliminate the need for Forminator.
   1. This would require another db to be made at start-up to track everything.
   2. Make use of a shortcode to be able to include the page on the front end.
   3. This page could allow for conditional rendering to allow a user to change the XML link once the submit it, and stop them from adding more than one link.
   4. This page could also include file uploading for company images and social links. Both of these would be added to meta data.
2. Optimize the code more to speed up the plugin.
3. Clean up commenting in code to make it more clear.
4. Clean up files to be closer to the standard plugin layout
5. Allow for picture upload. Files have to be uploaded to the job manager file.
