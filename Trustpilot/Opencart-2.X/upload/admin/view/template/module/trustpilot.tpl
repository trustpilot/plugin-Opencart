<?php echo $header; ?>

<script>const TRUSTPILOT_INTEGRATION_APP_URL = '<?php echo $TRUSTPILOT_INTEGRATION_APP_URL; ?>'; </script>

<?php echo $column_left; ?>

<div id="content" tabindex="0">
    <div class="page-header">
        <div class="container-fluid">
            <div class="pull-right" style='display: none'>
                <button type="submit" form="form-carousel" data-toggle="tooltip" title="<?php echo $button_save; ?>" class="btn btn-primary"><i class="fa fa-save"></i></button>
                <a href="<?php echo $cancel; ?>" data-toggle="tooltip" title="<?php echo $button_cancel; ?>" class="btn btn-default"><i class="fa fa-reply"></i></a>
            </div>
            <ul class="breadcrumb">
                <?php foreach ($breadcrumbs as $breadcrumb) { ?>
                <li><a href="<?php echo $breadcrumb['href']; ?>"><?php echo $breadcrumb['text']; ?></a></li>
                <?php } ?>
            </ul>
        </div>
        <div class="panel panel-default">
            <div class="container-fluid">
                <div class="panel-body">
                    <form action="<?php echo $action; ?>" method="post" enctype="multipart/form-data" id="form-carousel" class="form-horizontal" style='display: none'>
                        <div class="form-group required">
                            <label class="col-sm-2 control-label" for="trustpilot_master_settings_field">trustpilot_master_settings_field<span data-toggle="tooltip" title="trustpilot_master_settings_field"></label>
                            <div class="col-sm-10">
                                <input type="text" name="settings" value="<?php echo $trustpilot_master_settings_field; ?>" placeholder="trustpilot_master_settings_field" class="form-control"/>
                            </div>
                        </div>
                    </form>

                    <fieldset id="trustpilot_signup">
                        <script type='text/javascript'>
                            function onTrustpilotIframeLoad() {
                                if (typeof sendSettings === 'function' && typeof sendPastOrdersInfo === 'function') {
                                    sendSettings();
                                    sendPastOrdersInfo();
                                } else {
                                    window.addEventListener('load', function () {
                                        sendSettings();
                                        sendPastOrdersInfo();
                                    });
                                }
                            }
                        </script>
                        <iframe
                            src='<?php echo $TRUSTPILOT_INTEGRATION_APP_URL; ?>'
                            id='configuration_iframe'
                            frameborder='0'
                            scrolling='no'
                            width='100%'
                            height='1400px'
                            data-source='OpenCart'
                            data-settings='<?php echo $settings; ?>'
                            data-transfer='<?php echo $TRUSTPILOT_INTEGRATION_APP_URL; ?>'
                            data-past-orders='<?php echo $past_orders_info; ?>'
                            data-plugin-version='<?php echo $plugin_version; ?>'
                            data-version='OpenCart-<?php echo $version; ?>'
                            data-page-urls='<?php echo $page_urls; ?>'
                            data-product-identification-options='<?php echo $product_identification_options; ?>'
                            data-configuration-scope-tree='<?php echo $configuration_scope_tree; ?>'
                            data-plugin-status='<?php echo $plugin_status; ?>'
                            data-is-from-marketplace='<?php echo $is_from_marketplace; ?>'
                            onload='onTrustpilotIframeLoad();'>
                        </iframe>
                        <div id='trustpilot-trustbox-preview'
                            hidden='true'
                            data-page-urls='<?php echo $page_urls; ?>'
                            data-custom-trustboxes='<?php echo $custom_trustboxes; ?>'
                            data-settings='<?php echo $settings; ?>'
                            data-src='<?php echo $starting_url; ?>'
                            data-source='OpenCart'
                            data-sku='<?php echo $sku; ?>'
                            data-name='<?php echo $name; ?>'
                        >
                        </div>
                    </fieldset>
                    <script src='<?php echo $trustbox_preview_url; ?>' id='TrustBoxPreviewComponent'></script>
                </div>
            </div>
        </div>
    </div>
</div>

<?php echo $footer; ?>

