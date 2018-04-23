/**
 * PClib JavaScript helper functions.
 * - form validator
 * - ajax support
 * - pctree
 *
 * @author -dk- <lenochware@gmail.com>
 * http://pclib.brambor.net/
 */
 
/*
# This library is free software; you can redistribute it and/or
# modify it under the terms of the GNU Lesser General Public
# License as published by the Free Software Foundation; either
# version 2.1 of the License, or (at your option) any later version.
*/

/* Namespace for pclib functions. */
var pclib = {

xhr: null,

/** Occurs after ajax call is complete. */
onAjaxComplete: null,

/** Occurs when form is validated. */
onValidate: null,

ajax_id: null,
modalWin: null,

strings: {
	'form-err-date'   : ' Chybný datum!',
	'form-err-mail'   : ' Chybná emailová adresa!',
	'form-err-number' : ' Není číslo!',
	'form-err-passw'  : ' Nevalidní heslo!',
	'form-err-file'   : ' Chybný typ souboru!',
	'form-err-req'    : ' Pole je povinné!',
	'form-err-pattern' : ' Chybně zadaná hodnota!'
},

/** @private */
getFormElements: function(form) {
	var data = form.pclib_jsvalid.value.split('|');
	var elements = [];

	var i = 0;
	while(i < data.length) {
			var elem = {
			id: data[i++],
			lb: data[i++],
			required: (data[i++] == 1),
			rule: data[i++],
			options: data[i++],
		};

		elem.value = this.getValue(elem.id);

		elements.push(elem);
	}

	return elements;
},

/**
* Validate form and return result.
*
* @param {Object} form Source DOM object
* @return {Object} result {isValid, elements}
*/
validateForm: function(form) {
	var elements = this.getFormElements(form);
	var isValid = true;

	for (i in elements) {

		var elem = elements[i];

		if (elem.required && !elem.value) {
			elem.message = 'form-err-req';
			isValid = false;
		}

		if (!elem.value) continue;

		switch (elem.rule) {
			case 'email':
				if (!elem.value.match(/^[^@]+@[^.]+\..+$/)) {
					elem.message = 'form-err-mail';
					isValid = false;
				}
				break;
			case 'date':
				var pp = '';
				for (var j = 0; j < elem.options.length; j++) {
					if (j > 0) pp += "([^0-9]+)";
					switch (elem.options.charAt(j)) {
						case 'd': pp += "(0?[1-9]|[012][1-9]|[12][0-9]|[3][01])"; break;
						case 'm': pp += "(0?[1-9]|[1][012])"; break;
						case 'Y': pp += "([0-9]{4})"; break;
						case 'y': pp += "([0-9]{2})"; break;
						case 'H': pp += "(0?[0-9]|[01][0-9]|[2][0-3])"; break;
						case 'M': pp += "(0?[0-9]|[0-5][0-9])"; break;
						case 'S': pp += "(0?[0-9]|[0-5][0-9])"; break;
					}
				}

				if (!elem.value.match('^' + pp + '$')) {
					elem.message = 'form-err-date';
					isValid = false;
				}
				break;
			case 'number':
				if (!elem.value.match(/^[0-9]+\.?[0-9]*$/)) {
					elem.message = 'form-err-number';
					isValid = false;
				}
				break;
			case 'password':
				var result = true;
				var options = elem.options.split(',');
				if (options[0] && elem.value.length < parseInt(options[0])) result = false;
				if (options[1] && /^[a-z0-9]+$/i.test(elem.value)) result = false;

				if (!result) {
					elem.message = 'form-err-passw';
					isValid = false;
				}
				break;
			case 'file':
				var result = false;
				var patterns = elem.options.split(';');
				for (var j in patterns) {
					if (elem.value.match('^' + patterns[j] + '$')) {result = true; break;}
				}
				if (!result) {
					elem.message = 'form-err-file';
					isValid = false;
				}
				break;
			case 'pattern':
				if (!elem.value.match('^' + elem.options + '$')) {
					elem.message = 'form-err-pattern';
					isValid = false;
				}
				break;
		}
		elements[i] = elem;
	}

	return {isValid: isValid, elements: elements};
},

/**
* Form validation. Alert error messages, if form is invalid.
* It is called at form.onsubmit event when form has attribute 'jsvalid'.
*
* @param {Object} form Source DOM object
*/
validate: function(form)
{
	if (this.onValidate) {
		var ret = this.onValidate(form);
	}
	else {
		var ret = this.validateForm(form);
	}

	if (!ret.isValid) {
		this.showErrors(form, ret.elements);
	}

	return ret.isValid;
},

/** 
 * Show validation errors using alert().
 * @param {Object} form Source DOM object
 * @param {Array} elements Validation rules and messages for form fields - see getFormElements()
 */
showErrors: function(form, elements) {
	var message = '';

	for(i in elements) {
		if (elements[i].message) {
			message += elements[i].lb +' '+this.strings[elements[i].message]+"\n";
		}
	}

	alert(message);
},

/** @private */
getArrayValue: function (id) {
	var i = 0;
	var checkedArray = [];
	while (true) {
		var elem = document.getElementById(id + '_' + i++);
		if (elem === null) break;
		if(elem.checked) checkedArray.push(elem.value);
	}

	return checkedArray.join();
},

/** @private */
getValue: function (id) {
	var input = document.getElementById(id);
	if (input) return (input.type == 'radio' || input.type == 'checkbox')? input.checked : input.value;
	if (document.getElementById(id+'_0')) return this.getArrayValue(id);
	return false;
},

/** @private */
ajaxLoad: function (elem, url) {
	if (!this.xhr) this.xhr = new XMLHttpRequest();
	this.xhr.onreadystatechange = function(){
		if (this.readyState!=4 || this.status!=200) return;
		elem.innerHTML = this.responseText;
	};
	this.xhr.open('GET', url, true);
	this.xhr.send();
},

/**
 * Invoke ajax request - used by element's attribute ajaxget.
 *
 * @param {string} name template name
 * @param {string} id id of calling element
 * @param {string} url URL called by ajax. Should contain calling of function form->ajaxsync()
 * @param {string} params Comma separated list of elements' id for synchronization
 */
ajaxGet: function (name, id, url, params) {
	if (!this.xhr) this.xhr = new XMLHttpRequest();

	params += ',' + id;
	var paramsArray = params.split(',');
	var data = '&submitted='+name;
	for (var i in paramsArray) {
		data += '&data[' + paramsArray[i] + ']=' + this.getValue(paramsArray[i]);
	}

	this.ajax_id = id;
	var self = this;
	this.xhr.onreadystatechange = function() { self.ajaxSync(); };
	this.xhr.open('GET', url + data,true);
	this.xhr.send();
},

/**
 * Callback function for ajaxGet() - load data from the server.
 * @private
 */
ajaxSync: function() {
	if (this.xhr.readyState!=4 || this.xhr.status!=200) return;
	if (!this.xhr.responseText) return;
	var responseArray = JSON.parse(this.xhr.responseText);
	for (var id in responseArray) {
		var elem = document.getElementById(id);
		if (!elem) continue;
		elem.innerHTML = responseArray[id];
	}
	if (this.onAjaxComplete) this.onAjaxComplete(this.ajax_id);
},

/**
 * Onclick handler for pctree.
 * @private
 */
toggleTreeNode: function() {
	this.parentNode.className = (this.parentNode.className == 'folder open')? 'folder closed':'folder open';
},

/** Initialize all trees on your page.*/
initTree: function(className) {
	className = className || 'pctree';
	var nodes = document.querySelectorAll('ul.'+className+' li.folder>span');
	
	for (var i = 0; i < nodes.length; i++) {
		nodes[i].onclick = pclib.toggleTreeNode;
		nodes[i].className = 'label';
	}
},

showModal: function(id, url) {
	document.getElementById('pc-overlay').style.display='block';
	this.modalWin = document.getElementById(id);
	this.modalWin.style.display = 'block';
	this.ajaxLoad(this.modalWin, url);
},

hideModal: function() {
	document.getElementById('pc-overlay').style.display='none';
	this.modalWin.style.display='none';
},

buildQuery: function(parameters) {
	var qs = "";
	for(var key in parameters) {
		var value = parameters[key];
		qs += encodeURIComponent(key) + "=" + encodeURIComponent(value) + "&";
	}
	if (qs.length > 0){
		qs = qs.substring(0, qs.length-1);
	}
	return qs;
},

/** 
 * Convert pclib route string to url.
 *
 * @param {string} rs Route - for example 'products/edit/id:{#ID}'
 */
getUrl: function(rs) {
	var ra = rs.split("/");
	var route = [];
	var params = {};
	var param;
	for (var i = 0; i < ra.length; i++) {
		if (ra[i].indexOf(':') == -1) route.push(ra[i]);
		else {
			param = ra[i].split(":");
			if (param[1].charAt(0) == '{') {
				param[1] = document.getElementById(param[1].substring(2,param[1].length-1)).value;
			}
			params[param[0]] = param[1];
		}
	}
	var url = "index.php?r=" + route.join("/");
	if(typeof param != "undefined") url = url + '&' + this.buildQuery(params);
	return url;
},

/**
 * Redirect to route rs.
 *
 * @param {string} rs Route
 * @see {@link getUrl}
 */
redirect: function(rs) {
	window.location = this.getUrl(rs);
},

};