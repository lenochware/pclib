<?elements
class tpl
bind status list "0,new,1,scheduled,2,submitted,3,failed"
string created_at
string body noescape
?>
From: {from}<br>
To: {to}<br>
CC: {cc}<br>
Subject: {subject}<br>
[{attachments}]<br>
Status: {status}<br>
Created at: {created_at}<br>
Send at: {send_at}
<hr>
<p>{body}</p>
