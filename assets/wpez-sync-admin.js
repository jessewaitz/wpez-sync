// tab navigation.
var tab_link_wrapper = document.getElementById('nav-tab-wrapper');
var tab_link_all = document.querySelectorAll('#nav-tab-wrapper a');
var tab_content_all = document.querySelectorAll('#tabs .tab-content');

for (var i=0; i < tab_content_all.length; i++) { hide(tab_content_all[i]); }
show(tab_content_all[0]);
tab_link_all[0].classList.add('nav-tab-active');

tab_link_wrapper.addEventListener('click', function (event) {
	if (!event.target.matches('.nav-tab')) return;
	event.preventDefault();
	for (var i=0; i < tab_link_all.length; i++) { tab_link_all[i].classList.remove('nav-tab-active'); }
	for (var i=0; i < tab_content_all.length; i++) { hide(tab_content_all[i]); }
	event.target.classList.add('nav-tab-active');
	show(document.getElementById(event.target.dataset.ref));
	add_ace_editor();
}, false);

// add - remove remotes
function show(elem) {
	if (elem != null) {
		elem.style.display = 'block';
	}
};
function hide(elem) {
	if (elem != null) {
		elem.style.display = 'none';
	}
};
function remove_remote($key) {
	const remove_ul = document.getElementById('remote_list'); //console.log(remove_ul);
	const remove_li = remove_ul.children[$key]; //console.log(remove_li);
	remove_li.remove();
}
function add_new_remote() {
	// detect the last li element
	const last_li = document.getElementById('remote_list').lastElementChild;
	// get the flagKey from the input field
	const new_key = parseInt(last_li.dataset.key) + 1;
	// create the new elements
	const li_element = document.createElement('li');
	const input_flag = document.createElement('input');
	const input_url = document.createElement('input');
	const select_location = document.createElement("select");
	const select_option1 = document.createElement("option");
	const select_option2 = document.createElement("option");
	const select_option3 = document.createElement("option");
	// option values
	select_option1.value = "";
	select_option2.value = "remote";
	select_option3.value = "local";
	// option labels
	select_option1.text = "Select";
	select_option2.text = "Remote";
	select_option3.text = "Local";
	// set the type
	input_flag.type = "text";
	input_url.type = "text";
	// set the type
	input_flag.size = 6;
	input_url.size = 30;
	// set the name attribute
	input_flag.name = "wpez_sync_settings[remotes][" + new_key + "][flag]";
	input_url.name = "wpez_sync_settings[remotes][" + new_key + "][url]";
	select_location.name = "wpez_sync_settings[remotes][" + new_key + "][location]";
	// set empty value
	input_flag.value = "";
	input_url.value = "";
	// set placeholders
	input_flag.placeholder = "ie. live";
	input_url.placeholder = "ie. https://wpeztools.com";
	// add options to the select menu.
	select_location.add(select_option1, null);
	select_location.add(select_option2, null);
	select_location.add(select_option3, null);
	// add the new advertising code to the list element
	li_element.dataset.key = new_key;
	li_element.appendChild(input_flag);
	li_element.append("\u00A0");
	li_element.appendChild(input_url);
	li_element.append("\u00A0");
	li_element.appendChild(select_location);
	// add the list element to the list
	document.getElementById('remote_list').appendChild(li_element);
}

// add ace editor
function add_ace_editor() {
	var textareas = document.querySelectorAll('textarea[data-editor]');
	textareas.forEach(function(textarea) {
		var mode = textarea.getAttribute('data-editor');
		var editDiv = document.createElement('div');
		editDiv.style.position = 'absolute';
		editDiv.style.width = textarea.offsetWidth + 'px';
		editDiv.style.height = textarea.offsetHeight + 'px';
		editDiv.className = textarea.className;
		
		textarea.parentNode.insertBefore(editDiv, textarea);
		
		var editor = ace.edit(editDiv);
		editor.renderer.setShowGutter(textarea.getAttribute('data-gutter'));
		editor.getSession().setValue(textarea.value);
		editor.getSession().setMode("ace/mode/" + mode);
		editor.setTheme("ace/theme/github_dark");
		
		// Copy back to textarea on form submit
		textarea.closest('form').addEventListener('submit', function() {
			textarea.value = editor.getSession().getValue();
		});
	});
}
document.addEventListener('DOMContentLoaded', function () {
	add_ace_editor();
});