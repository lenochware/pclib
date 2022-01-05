<?elements
string ID
string LABEL
string LEVEL
string OPEN
string URL
string CSS_CLASS default "tree"
string TREE_ID
string items
?>
{block root}
	<ul id="tree{TREE_ID}" class="{CSS_CLASS}">{items}</ul>
{/block}

{block folder}
<li id="i{ID}" class="folder {OPEN}">
	<span>
		{if URL}<a href="{URL}">{LABEL}</a>{/if}
		{if not URL}{LABEL}{/if}
	</span>
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