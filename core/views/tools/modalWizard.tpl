
{include file="[Core]/form/validators.tpl"}
<div class="modal-header">
<button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
<h4>
{if isset($modalTitle)}
  {$modalTitle}
{else}
  {t}Add{/t}
{/if}
</h4>
</div>
<div class="flash alert fade in" id="modal-flash-message" style="display: none;">
  <button type="button" class="close" aria-hidden="true">&times;</button>
  <ul id="{$name}_errors"></ul>
</div>
<div class="wizard" id="{$name}">
  <ul class="steps">
    {foreach $steps as $step}
    <li data-target="#{$name}_{$step@index + 1}"{if $step@index == 0} class="active"{/if}><span class="badge badge-info">{$step@index + 1}</span>{$step@key}<span class="chevron"></span></li>
    {/foreach}
  </ul>
</div>
<div class="row-divider"></div>
<form role="form" class="CentreonForm" id="wizard_form" data-route="{$currentRoute}">
  <div class="step-content">
   {foreach $steps as $step}
   <div class="step-pane{if $step@index == 0} active{/if}" id="{$name}_{$step@index + 1}">
     <div class="row">
     {foreach $step['default'] as $component}
       <div class="col-xs-{$component['width']}">
       {$formElements[$component['name']]['html']}
       </div>
     {/foreach}
     </div>
   </div>
   {/foreach}
  </div>
  <div class="modal-footer">
    {$formElements.hidden}
    <button class="btnC btnDefault btn-prev" disabled>{t}Prev{/t}</button>
    <button class="btnC btnDefault btn-next" data-last="{t}Finish{/t}" id="wizard_submit">{t}Next{/t}</button>
  </div>
</form>
<script>
var modalListener;
$(function() {
  $(document).unbind('finished');
  {if isset($validateUrl)}
  $(document).on('finished', function (event) {
    if ($('#wizard_form').valid()) {
      $.ajax({
        url: "{url_for url=$validateUrl}",
        type: "POST",
        dataType: 'json',
        data: $("#wizard_form").serializeArray(),
        context: document.body
      })
      .success(function(data, status, jqxhr) {
        if(!isJson(data)){
            alertMessage( "{t} An Error Occured {/t}", "alert-danger" );
            return false;
        }
        alertModalClose();
        if (data.success) {
          {if isset($formRedirect) && $formRedirect}
            window.location='{url_for url=$formRedirect}';
          {else}
            alertModalMessage("The object has been successfully saved", "alert-success");
          {/if}
          $('#modal').modal('hide');
          if (typeof oTable != 'undefined') {
            oTable.fnDraw();
          }
        } else {
          alertModalMessage(data.error, "alert-danger");
        }
      }).error(function (error){
        alertModalMessage( "{t} An Error Occured {/t}", "alert-danger" );
      });
      return false;
    }
  });
  {else}
  $(document).on('finished', function (event) {
    $('#modal').modal('hide');
  });
  {/if}
  {get_custom_js}
  loadParentField();
  $("#wizard_form").centreonForm({
    rules: (formValidRule["{$formName}"] === undefined ? {} : formValidRule["{$formName}"])
  });
});

</script>

