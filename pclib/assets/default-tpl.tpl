<?elements
string elements noescape
?>
<:?elements
class tpl
{elements}
?:>
<table class="tpl">
{block columns}<tr>
  <td>{:{name}.lb:}:</td>
  <td>{:{name}:}</td>
</tr>
{/block}
</table>