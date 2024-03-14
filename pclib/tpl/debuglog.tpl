<?elements
class grid name "LOGGER"
string category
string message noescape
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
