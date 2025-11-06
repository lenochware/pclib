<?elements
class grid name "db-dump" singlepage
pager pager pglen "100"
?>
<table class="db-dump">
  <tr>{grid.labels}</tr>
{BLOCK items}
  <tr>{grid.fields}</tr>
{/BLOCK}
</table>