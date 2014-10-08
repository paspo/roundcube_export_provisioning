/* Show export_provisioning plugin script */

if (window.rcmail) {
  rcmail.addEventListener('init', function(evt) {
    // <span id="settingstabdefault" class="tablink"><roundcube:button command="preferences" type="link" label="preferences" title="editpreferences" /></span>
    var tab = $('<span>').attr('id', 'settingstabpluginexport_provisioning').addClass('tablink');

    var button = $('<a>').attr('href', rcmail.env.comm_path+'&_action=plugin.export_provisioning').html(rcmail.gettext('export_provisioning', 'export_provisioning')).appendTo(tab);
    button.bind('click', function(e){ return rcmail.command('plugin.export_provisioning', this) });

    // add button and register command
    rcmail.add_element(tab, 'tabs');
    rcmail.register_command('plugin.export_provisioning', function(){ rcmail.goto_url('plugin.export_provisioning') }, true);
  })
}

function export_provisioning_changelink(sel) {
  var value = sel.value;
  $('#export_provisioning_download_ios').attr("href", "./?_task=settings&_action=plugin.export_provisioning-download-ios&_identity="+value);
  $('#export_provisioning_download_iaf').attr("href", "./?_task=settings&_action=plugin.export_provisioning-download-iaf&_identity="+value);
}
