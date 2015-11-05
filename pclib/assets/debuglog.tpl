<?elements
class grid name "LOGGER"
string DT date "%H:%M:%S" lb "TIME"
string LOGGERNAME
string ACTIONNAME
bind COLOR field "ACTIONNAME" list "url,green,error,red,*,black"
string MESSAGE
?>
<table>
{block items}
<tr>
<td valign="top" style="color:gray">{DT}</td>
<td style="color:{COLOR}">{MESSAGE}</td>
</tr>
{/block}
</table>
