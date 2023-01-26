<?elements
class templatefactory create "grid" sort
string elements noescape
string name
?>
<:?elements
class grid
{elements}
pager pager pglen "20"
?:>
<table class="grid">
  <tr>
  {:grid.labels:}
  </tr>
{:block items:}
  <tr>
  {:grid.fields:}
  </tr>
{:/block:}
</table>
<div class="pager">{:pager:}</div>
