
$(document).ready(function() {
	
	if ($('#AccessControl_Left').size() == 0) {
		return;
	}
	
	var openReportAdministration, apiMethod;
	var userLogin = 'anonymous';
	var tokenAuth = 'anonymous';
	var userSelect = $('#AccessControl_UserSelect');
	var siteSelect = $('#AccessControl_SiteSelect');
	var idSite = siteSelect.val();
	var left = $('#AccessControl_Left');
	var right = $('#AccessControl_Right');
	var loading = right.find('div.Loading').show();
	var config = right.find('div.Config').hide();
	
	/** Pick a user */
	userSelect.change(function() {
		right.hide();
		left.find('a.Active').removeClass('Active');
		
		var tempUserLogin = $(this).val();
		
		// load auth token for preview links
		$.get(piwik.piwik_url + 'index.php', {
			module: 'API',
			method: 'UsersManager.getUser',
			userLogin: tempUserLogin,
			format: 'json',
			token_auth: piwik.token_auth
		}, function(response) {
			if (typeof response[0] == 'undefined' || typeof response[0].token_auth == 'undefined') {
				alert('Could not load user\'s auth token!');
			} else {
				userLogin = tempUserLogin;
				tokenAuth = response[0].token_auth;
			}
		}, 'json');
	});
	
	/** Pick a site */
	siteSelect.change(function() {
		right.hide();
		left.find('a.Active').removeClass('Active');
		idSite = $(this).val();
	});
	
	/** Pick a report */
	$('#AccessControl_Left a.Report').click(function() {
		var link = $(this);
		
		var reportName = link.html();
		apiMethod = link.attr('method');
		
		link.parent().parent().find('a.Active').removeClass('Active');
		link.addClass('Active');
		
		openReportAdministration(reportName);
		
		return false;
	});
	
	/** Show configuration */
	openReportAdministration = function(reportName) {
		right.show();
		config.hide();
		loading.show();
		
		var user = userSelect.val();
		
		var spans = right.find('.Title span');
		spans.eq(0).html($.trim(reportName));
		spans.eq(1).html(user);
		
		$.get(piwik.piwik_url + 'index.php', {
			module: 'API',
			method: 'AccessControl.getAccessConfiguration',
			apiMethod: apiMethod,
			userName: user,
			idSite: idSite,
			token_auth: piwik.token_auth,
			format: 'json'
		}, function(response) {
			var value = response.value ? response.value : '';
			value = value.replace(/;;;/g, "\n");
			config.show().find('textarea').val(value);
			loading.hide();
		}, 'json');
	};
	
	/** Save the configuration */
	config.find('input').click(function() {
		var el = $(this);
		var originalValue = el.val();
		
		el.val('...').attr('disabled', 'disabled');
		
		var configValue = config.find('textarea').val();
		configValue = configValue.replace(/\n/g, ';;;');
		if ($.trim(configValue) == '') {
			configValue = 'none';
		}
		
		$.post(piwik.piwik_url + 'index.php', {
			module: 'API',
			method: 'AccessControl.setAccessConfiguration',
			apiMethod: apiMethod,
			userName: userSelect.val(),
			config: configValue,
			idSite: idSite,
			token_auth: piwik.token_auth,
			format: 'json'
		}, function() {
			el.removeAttr('disabled').val(originalValue);
		});
	});
	
	/** Preview API call */
	config.find('.Preview a').click(function() {
		
		var url = piwik.piwik_url + 'index.php' + '?module=API&method=' + apiMethod + '&idSite=' + idSite
				+ '&period=day&date=yesterday&format=xml&token_auth=' + tokenAuth;
		
		window.open(url);
		
		return false;
	});
	
});