<style type="text/css">
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

#pc-overlay {
  display:none;
  position: fixed;
  top: 0vh;
  left: 0vw;
  width: 100vw;
  height: 100vh;
  background: #000;
  opacity: 0.1;
  filter: alpha(opacity=50);
  z-index: 1500;
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

.pc-debugbar-window table td {
  padding:2px;
}

.pc-debugbar-menu {
  position:fixed;
  background-color: white; 
  width: 80%;
  padding: 10px 0px;
}
</style>
<script>
  function pc_debugbar_resize(top, height)
  {
    var elem = document.getElementById('pcwin0');
    elem.style.top = top;
    elem.style.height = height;
  }
</script>

<div id="pc-overlay" onclick="pclib.hideModal();event.stopPropagation()"></div>
<div id="pc-debugbar" onclick="pclib.showModal('pcwin0','?r=pclib_debugbar/show');event.stopPropagation()">
  <a href="#" onclick="document.getElementById('pc-debugbar').style.display='none';event.stopPropagation()">×</a>
  pclib {VERSION}|{TIME} ms|{MEMORY} MB
</div>
<div id="pcwin0" class="pc-debugbar-window"></div>