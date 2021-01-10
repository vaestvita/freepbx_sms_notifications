# FreePBX SMS message sender
Small php-file send your message from FreePBX to your Android SMS Gateway (eg MacroDroid, webhook trigger)

Capabilities:
1. Sending an SMS business card to your clients after a call
2. sending a notification of a missed call to the operator (to the mobile number specified in findmefollow)

Installation and configuration

copy files to folder /var/lib/asterisk/bin/sms/
go to /var/lib/asterisk/bin/
chown asterisk:asterisk -R /sms

Go to https://my-pbx-server.com/admin/config.php?display=advancedsettings  (FreePBX Advanced Settings)
copy this code "/usr/bin/php /var/lib/asterisk/bin/sms/sms.php ^{CALLERID(name)} ^{ARG3}"  to the field  "Post Call Recording Script"
Submit, Apply Config


install the app MacroDroid (https://play.google.com/store/apps/details?id=com.arlosoft.macrodroid) on your phone 
create a macro 

1. use a webhook as a trigger (copy the received address to a file sms.php, $url = 'https://trigger.macrodroid.com/xxxxxxxxx-xxxxxxx-xxxxxx/smsgate'; //sms gateway address)
2. Create a local variables (smsbody, smsto, smstype)
3. create actions "Messaging - Send SMS" 
4. in the field "phone number" insert local variable "smsto" ([v=smsto]
5. in the field "Message text" insert local variable "smsbody" ([v=smsbody])
6. save and enable your macro
