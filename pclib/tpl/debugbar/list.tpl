<?elements
class grid name "debuglog"
string category
string message noescape
string time
pager pager pglen "1000"
?>
<table class="pc-debuglog">
	{block items}
	<tr class="{category}">
		<td>{category}</td>
		<td>{message}</td>
	</tr>
	{/block}
</table>
