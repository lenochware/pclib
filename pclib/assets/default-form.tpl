<?elements
class templatefactory create "form"
string elements noescape
?>
<:?elements
class form
{elements}
button insert lb "Insert" noprint
button update lb "Update" noprint
button delete lb "Delete" noprint
?:>
<table class="form">
{block columns}<tr>
  <td>{:{name}.lb:}</td>
  <td>{:{name}:}</td>
  <td>{:{name}.err:}</td>
</tr>
{/block}
<tr><td colspan="3">{:insert:} {:update:} {:delete:}</td></tr>
</table>