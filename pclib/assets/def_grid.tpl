<:?elements
class grid name "{NAME}"
{ELEMENTS}
pager pager pglen "20"
?:>
<table class="grid" id="{NAME}">
  <tr>
  {block HEAD}<th>{LABEL}</th>
  {/block}</tr>
{:block items:}
  <tr>
  {block BODY}<td>{FIELD}</td>
  {/block}</tr>
{:/block:}
</table>
<div class="pager">{:pager:}</div>
