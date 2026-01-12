Take advantage of Advacheck’s plagiarism checker from the comfort of your Moodle LMS.
The Advacheck plugin for Moodle allows educators to seamlessly submit student responses from assignments, forums, tests, or lectures for plagiarism review. Checks can be performed manually as needed or set to run automatically. You can quickly view a summary of the plagiarism check results alongside the student’s response in the course element and access a link to the full report for in-depth analysis.

**IMPORTANT NOTICE**:
To utilize the Advacheck plugin, you must have an active account on Advacheck.com which requires a commercial subscription. 
Please [contact us](https://advacheck.com/contacts/) to set up an account.
The default connection settings are configured for the demo version.

### INSTALLING
There are no special system requirements for this plugin, but the performance of the current version was tested on Moodle 3.9 in the minimum configuration (PHP 7.3 + MySQL 5.6 or PostgreSQL 9.5), the correct operation of the plugin on lower versions of software is not guaranteed.

------------

WARNING:
Prior to installing this plugin, please make sure that the Moodle user has the right to create views within the Moodle database. Lack of this permission could lead to installation errors and issues during plugin operation. It is essential to add these permissions if they are not already in place.

------------

After setting permissions in the database for the Moodle user, the plugin can be installed in the usual way (via CLI or from a zip file).

After installing the plugin, make sure that enableplagiarism parameter is enabled in Moodle settings (Administration - Advanced features - Enable plagiarism plugins).

Then go to Administration - Plugins - Plagiarism - Advacheck and enable our plugin (check Enable the Advacheck).

After that you can configure the plugin according to the credentials obtained on [our site](https://advacheck.com) or activate the test connection by setting the default settings.
Detailed instructions for configuring the plugin are available on [our website](https://manual.advacheck.com/main/docs/advacheck_plugin_documentation.pdf#%5B%7B%22num%22%3A32%2C%22gen%22%3A0%7D%2C%7B%22name%22%3A%22XYZ%22%7D%2C33%2C805%2C0%5D).

