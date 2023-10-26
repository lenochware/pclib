<?elements
class grid name "LOGGER"
string DT date "%H:%M:%S" lb "TIME"
string LOGGERNAME
string ACTIONNAME
bind COLOR field "ACTIONNAME" list "url,green,error,red,*,black"
string MESSAGE noescape
pager pager pglen "1000"
?>
<table>
{block items}
<tr>
<td style="color:{COLOR}">{MESSAGE}</td>
</tr>
{/block}
</table>
