<?elements
class templatefactory create "grid"
?>
<:?elements
class grid
{elements}
pager pager
?:>
{block head}{:{name}.lb:}{if not @block_bottom}<csv-separ>{/if}{/block}
<csv-row-separ>
{:block items:}
{block columns}{:{name}:}{if not @block_bottom}<csv-separ>{/if}{/block}
{:if not @block_last:}<csv-row-separ>{:/if:}
{:/block:}