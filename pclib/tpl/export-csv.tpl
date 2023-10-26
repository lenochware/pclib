<?elements
class templatefactory create "grid"
?>
<:?elements
class grid
{elements}
pager pager
?:>
{block head}{:{name}.lb:}{if not _tvar_bottom}<csv-separ>{/if}{/block}
<csv-row-separ>
{:block items:}
{block columns}{:{name}:}{if not _tvar_bottom}<csv-separ>{/if}{/block}
{:if not _tvar_last:}<csv-row-separ>{:/if:}
{:/block:}