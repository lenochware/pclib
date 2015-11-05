<:?elements
class tpl name "{NAME}"
{ELEMENTS}
?:>
<table class="tpl" id="{NAME}">
{block BODY}<tr>
  <td>{LABEL}:</td>
  <td>{FIELD}</td>
</tr>
{/block}
</table>
