<?elements
class templatefactory create "form"
string elements noescape
string name
?>
<:?elements
class form
{elements}
button insert lb "Insert" noprint
button update lb "Update" noprint
button delete lb "Delete" noprint
?:>
<table class="form">
{:form.fields:}
</table>