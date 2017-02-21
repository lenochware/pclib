<?elements
class templatefactory create "grid" sort
?>
<:?elements
class grid
{elements}
pager pager pglen "20"
?:>
<table class="grid">
  <tr>
  {block head}<th>{:{name}.lb:}</th>{/block}
  </tr>
{:block items:}
  <tr>
  {block columns}<td>{:{name}:}</td>{/block}
  </tr>
{:/block:}
</table>
<div class="pager">{:pager:}</div>
