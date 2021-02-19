# FreePBX SMS message sender
Send your SMS-message from FreePBX to your SMS Gateway (eg. MacroDroid, Goip SMS server)

Capabilities:
1. Sending an SMS business card to your clients after a call
2. sending a notification of a missed call to the operator (to the mobile number specified in findmefollow)
3. send the client number after calling the operator on the mobile phone

Installation and configuration

1. copy files to folder /var/lib/asterisk/agi-bin/sms
2. go to /var/lib/asterisk/agi-bin/
3. chown asterisk:asterisk -R /sms
4. add code to the end of the file extensions_custom.conf 

[macro-dial-ringall-predial-hook]
exten => s,1,Noop(Entering user defined context macro-dial-ringall-predial-hook in extensions_custom.conf)
exten => s,n,Set(CHANNEL(hangup_handler_push)=send_sms,s,1)
exten => s,n,MacroExit

[macro-dial-hunt-predial-hook]
exten => s,1,Noop(Entering user defined context macro-dial-hunt-predial-hook in extensions_custom.conf)
exten => s,n,Set(CHANNEL(hangup_handler_push)=send_sms,s,1)
exten => s,n,MacroExit

[send_sms]
exten => s,1,Noop(Entering user defined context send_sms in extensions_custom.conf)
exten => s,n,AGI(sms/sms.php,${CONNECTEDLINE(num)},${DIALSTATUS},${CDR(dstchannel)})
exten => s,n,Return

5. Submit, Apply Config


install the app MacroDroid (https://play.google.com/store/apps/details?id=com.arlosoft.macrodroid) on your phone 
create a macro 

1. use a webhook as a trigger (copy the received address to a file sms.php, $url = 'https://trigger.macrodroid.com/xxxxxxxxx-xxxxxxx-xxxxxx/smsgate'; //sms gateway address)
2. Create a local variables (smsbody, smsto, smstype)
3. create actions "Messaging - Send SMS" 
4. in the field "phone number" insert local variable "smsnum" ([v=smsnum]
5. in the field "Message text" insert local variable "Memo" ([v=memo])
6. save and enable your macro


If you are using Goip SMS server
1. disable this parameter in "System Manage":  "Save message before sending (browser should support javascript)" (http://goip-sms-server.com/en/sys.php)
