# FreePBX SMS notification sender

https://github.com/vaestvita/freepbx_sms_notifications

Sending SMS notifications from your FreePBX server via SMS gateway (Goip SMS server, MacroDroid (Android))

Capabilities:
1. Sending an SMS business card to your clients after a call (card.txt)
2. sending a notification of a missed call to the operator (to the mobile number specified in findmefollow) (notify.txt)
3. send the client number after calling the operator on the mobile phone (answered.txt)

Installation and configuration

1. copy files to folder /var/lib/asterisk/agi-bin/sms
2. go to /var/lib/asterisk/agi-bin/
3. chown asterisk:asterisk -R /sms
4. add code to the end of the file extensions_custom.conf 
5. Submit, Apply Config
6. Enter the address of your SMS gateway in the url field ($url = '')

SMS notifications after the call will be sent according to the matching scenario if there is SMS text in the corresponding file (card.txt, notify.txt, answered.txt)

# Examples
1. if you want to send an SMS business card for all clients who called the extension number 100, make a record in the file card.txt similar to this: "100;Thank you for your call. Visit our website: example.com"
2. if you want to send an SMS notification to an operator with extension number 100 to his mobile (which is specified in the follow me module) about a missed call, create an entry in the file notify.txt similar to: "100;Missed call"
3. If you want to send an SMS notification with the client's number to the operator who received the call on his mobile phone, create a corresponding entry in the file answered.txt: "100;Now call"

# SMS gateway setting

## If you are using Goip SMS server
1. disable this parameter in "System Manage":  "Save message before sending (browser should support javascript)" (/goip/en/sys.php)

## if you want to send SMS from your Android smartphone

1. Install the app MacroDroid (https://play.google.com/store/apps/details?id=com.arlosoft.macrodroid) 
2. Create a new macro 
3. Use a webhook as a trigger 
4. Copy the received address to a file sms.php, $url = 'https://trigger.macrodroid.com/xxxxxxxxx-xxxxxxx-xxxxxx/smsgate'; //sms gateway address)
5. Create a local variables (memo, smsnum)
6. create actions "Messaging - Send SMS" 
7. in the field "phone number" insert local variable "smsnum" ([v=smsnum]
8. in the field "Message text" insert local variable "Memo" ([v=memo])
9. save and enable your macro

If you know how to improve this script - make a request, I will be glad for your help.