<?elements
string VERSION
string TIME
string MEMORY
string POSITION
?>
<style>
#pc-debugbar {
  {POSITION}
  background:white;
  border:1px solid blue;
  padding: 2px;
  cursor:pointer;
  border-radius: 2px;
  opacity: 0.8;
  z-index: 2100;
}

.pc-debugbar-window {
  display: none;
  position:fixed;
  top: 65vh;
  left: 0px;
  right:0px;
  height: 35vh;
  overflow: scroll;
  background-color: white;
  border:1px solid blue;
  padding: 0px 10px;
  font-family: monospace;
  font-size: 12px;
  z-index: 2000;
}

.pc-debugbar-window table {
  width: 100%;
}

.pc-debugbar-window table td {
  padding: 2px;
  border-top: 1px solid #ddd;
  font-family: monospace;
  font-size: 12px;
}

.pc-debugbar-window table tr.url {
  background-color: #a3c7ff;
}

.pc-debugbar-window table tr.url td {
  padding: 10px 2px;
}

.pc-debugbar-errors {
  padding: 0px 4px;
  background-color: #d70022;
  color: white;
  border-radius: 8px;
  float:right;
  margin-left: 5px;
  font-size: 0.8rem;
  font-weight: bold;
  margin-top: 2px; 
}

.redirect {
  background-color: #deebff;
}

.pc-debugbar-menu {
  position:fixed;
  background-color: #f4f5f7; 
  width: calc(100vw - 50px);
  padding: 10px 0px;
}

.pc-debuglog tr.warning td:first-child, .pc-debuglog tr.error td:first-child {
  color:red;
}
</style>

<script>
function pc_debugbar_resize(top, height)
{
  var elem = document.getElementById('pcwin0');
  elem.style.top = top;
  elem.style.height = height;
}

async function pclibShowModal(id, url)
{
  pclibDebugWin = document.getElementById(id);
  pclibDebugWin.style.display = 'block';
  const response = await fetch(url);
  const responseText = await response.text();
  pclibDebugWin.innerHTML = responseText;
}

function pclibHideModal()
{
  pclibDebugWin.style.display='none';
}

window.addEventListener('load', function () {
  fetch('?r=pclib_debugbar/clear');
})
</script>

<div id="pc-debugbar" onclick="pclibShowModal('pcwin0','?r=pclib_debugbar/show');event.stopPropagation()">
  <a href="#" onclick="document.getElementById('pc-debugbar').style.display='none';event.stopPropagation()">×</a>
  pclib {VERSION}|{TIME} ms|{MEMORY} MB {if ERRORS}<div class="pc-debugbar-errors" title="Errors...">{ERRORS}</div>{/if}
</div>
<div id="pcwin0" class="pc-debugbar-window"></div>