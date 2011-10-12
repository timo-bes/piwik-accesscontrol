{assign var=showSitesSelection value=false}
{assign var=showPeriodSelection value=false}
{include file="CoreAdminHome/templates/header.tpl"}

<h2>{'AccessControl_AccessControl'|translate}</h2>
	
<div id="AccessControl_Left">
	<div id="AccessControl_User">
		{'AccessControl_SelectUser'|translate}<br />
		<select id="AccessControl_UserSelect" name="">
			{foreach from=$users item=user}
				<option value="{$user.login|escape:'html'}">{$user.login|escape:'html'}</option>
			{/foreach}
		</select>
	</div>
	
	<div id="AccessControl_Site">
		{'AccessControl_SelectSite'|translate}<br />
		<select id="AccessControl_SiteSelect" name="">
			{foreach from=$availableSites item=site}
				<option value="{$site.idsite}">{$site.name}</option>
			{/foreach}
		</select>
	</div>
	
	{foreach from=$availableReports key=category item=reports}
		<div class="CategoryContainer">
			<div class="Category">{$category|escape:'html'}</div>
			{foreach from=$reports item=report}
				<a class="Report" href="#" method="{$report.method}">
					{$report.name|escape:'html'}
				</a>
			{/foreach}
		</div>
	{/foreach}
</div>

<div id="AccessControl_Right">
	<div class="Title">
		{'AccessControl_AccessControlFor'|translate} 
		&quot;<span></span>&quot; 
		({'AccessControl_User'|translate} <span></span>)
	</div>
	<div class="Loading">{'General_Loading_js'|translate}</div>
	<div class="Config">
		<textarea></textarea>
		<br />
		<input type="submit" value="{'General_Save'|translate}" />
		<div class="Preview">
			<a href="#">{'AccessControl_TestAPICall'|translate}</a>
		</div>
	</div>
</div>	
	
{include file="CoreAdminHome/templates/footer.tpl"}
