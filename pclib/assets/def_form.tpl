<:?elements
class form name "{NAME}"
{ELEMENTS}
button insert lb "Insert" noprint
button update lb "Update" noprint
button delete lb "Delete" noprint
?:>
<table class="form" id="{NAME}">
{block BODY}<tr>
  <td>{LABEL}</td>
  <td>{FIELD}</td>
  <td>{ERR}</td>
</tr>
{/block}
<tr><td colspan="3">{:insert:} {:update:} {:delete:}</td></tr>
</table>
