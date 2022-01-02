create your credentials as described here:
https://www.fillup.io/post/read-and-write-google-sheets-from-php/


- Create project on https://console.developers.google.com/apis/dashboard.
- Click Enable APIs and enable the Google Sheets API
- Go to Credentials, then click Create credentials, and select Service account key
- Choose New service account in the drop down. Give the account a name, anything is fine.
- For Role I selected Project -> Service Account Actor
- For Key type, choose JSON (the default) and download the file. This file contains a private key so be very careful with it, it is your credentials after all
- Finally, edit the sharing permissions for the spreadsheet you want to access and share either View (if you only want to read the file) or Edit (if you need read/write) access to the client_email address you can find in the JSON file.