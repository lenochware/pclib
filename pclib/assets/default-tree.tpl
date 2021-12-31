<?elements
string ID
string LABEL
string OPEN
string items
?>
{block item}
	<li id="i{ID}">{LABEL}</li>
{/block}

{block folder}
<li id="i{ID}" class="folder {OPEN}">
	<span>{LABEL}</span>
	<ul>{items}</ul>
</li>
{/block}