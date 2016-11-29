function parseFTPRootDirectory(){
	var value = $('input[name=ftp_root_directory]').val();
	if(value.substring(value.length-1,value.length) != '/'){
		value += '/';
	}
	if(value.substring(0,1) != '/'){
		value = '/' + value;
	}
	$('input[name=ftp_root_directory]').val(value);
}

function findFTPRootDirectory(){
	if($('input[name=ftp_host]').val() != '' && $('input[name=ftp_username]').val() != '' && $('input[name=ftp_password]').val() != ''){
	    $('input[name=ftp_root_directory]').parent('div').attr('id','ftp_root_directory_div').html('...');

		$.get('./index.php?ajax=1&host=' + encodeURIComponent($('input[name=ftp_host]').val()) + '&port=' + encodeURIComponent($('input[name=ftp_port]').val()) + '&passive_mode=' + encodeURIComponent($('input[name=ftp_passive_mode]:checked').val()) + '&username=' + encodeURIComponent($('input[name=ftp_username]').val()) + '&password=' + encodeURIComponent($('input[name=ftp_password]').val()), function(data) {
			$('#ftp_root_directory_div').html(data);
		}).error(function(){
			$('#ftp_root_directory_div').html('<input type="text" class="form-control ftp_field" name="ftp_root_directory" tabindex="14" value="">');
		});
	}
}

function ftpEnabled(){
	if($('input[name=ftp_enabled][value=1]').is(':checked')){
		$(".ftp_field").removeAttr("disabled");
	}else{
		$(".ftp_field").attr("disabled", "disabled");
	}
}

var elems = Array.prototype.slice.call(document.querySelectorAll('.js-switch'));
elems.forEach(function(elem) {
  //var switchery = new Switchery(html);
  var switchery = new Switchery(elem, { size: 'small' });
});

$(document).ready(function(){

    // Tooltip
	$('[rel="tooltip"]').tooltip();

    // Responsive table
    if ($('#files-section').length) {
		$('#files-section').stacktable({myClass:'stacktable small-only'});
	}

	// FTP Enabled
	$('input[name=ftp_enabled]').change(function(){
		ftpEnabled();
	});
	ftpEnabled();

	// Parse FTP root directory
	$('input[name=ftp_root_directory]').change(function(){
		parseFTPRootDirectory();
	});

	// Find FTP root directory
	$('input[name=ftp_host],input[name=ftp_port],input[name=ftp_passive_mode],input[name=ftp_username],input[name=ftp_password]').change(function(){
		findFTPRootDirectory();
	});

	// Change language (installation)
	$('#controls select[name=language]').change(function(){
		$('#content form input[type=hidden][name=install]').val('0');
		var action = $('#content form').attr('action');
		$('#content form').attr('action',action.substr(0,action.indexOf('language=')+9) + $(this).val());
		$('#content form button[type=submit]').click();
	});
});