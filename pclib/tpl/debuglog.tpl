<?elements
class grid name "debuglog"
string category
string message noescape
string time
pager pager pglen "1000"
?>
<table>
	{block items}
	<tr class="{category}">
		<td>{category}</td>
		<td>{message}</td>
		<td>{time}</td>
	</tr>
	{/block}
</table>
