<?elements
string ID
string LABEL
string LEVEL
string OPEN
string URL
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
{if URL}
	<li id="i{ID}"><a href="{URL}">{LABEL}</a></li>
{/if}
{if not URL}
	<li id="i{ID}">{LABEL}</li>
{/if}
{/block}