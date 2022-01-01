<?elements
string ID
string LABEL
string OPEN
string CSS_CLASS default "pctree"
string items
?>
{block root}
	<ul class="{CSS_CLASS}">{items}</ul>
{/block}

{block folder}
<li id="i{ID}" class="folder {OPEN}">
	<span>{LABEL}</span>
	<ul>{items}</ul>
</li>
{/block}

{block item}
	<li id="i{ID}">{LABEL}</li>
{/block}