<style type="text/css">
#pc-debugbar {
  {POSITION}
  background:white;
  border:1px solid blue;
  padding: 2px;
  cursor:pointer;
  border-radius: 2px;
  opacity: 0.8;
}

#pc-overlay {
  display:none;
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: #000;
  opacity: 0.1;
  filter: alpha(opacity=50);
}

.pc-debugbar-window {
  display: none;
  position:absolute;
  top: 100px;
  left: 100px;
  width: 80%;
  height: 80%;
  overflow: scroll;
  background-color: white;
  border:1px solid blue;
  padding: 8px;
  font-family: monospace;
}
</style>
<div id="pc-overlay" onclick="pclib.hideModal()"></div>
<div id="pc-debugbar" onclick="pclib.showModal('pcwin0','?r=pclib/debuglog')">
<a href="#" onclick="document.getElementById('pc-debugbar').style.display='none';event.cancelBubble = true;">×</a>
pclib {VERSION}|{TIME} ms|{MEMORY} MB <a href="#" onclick="pclib.showModal('pcwin1','?r=pclib/debuginfo');event.cancelBubble = true;">info</a></div>
<div id="pcwin0" class="pc-debugbar-window"></div>
<div id="pcwin1" class="pc-debugbar-window"></div>