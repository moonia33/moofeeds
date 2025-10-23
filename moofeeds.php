<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

class Moofeeds extends Module
{
    public function __construct()
    {
        $this->name = 'moofeeds';
        $this->tab = 'advertising_marketing';
    $this->version = '1.0.4';
        $this->author = 'moonia';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = ['min' => '1.7.0.0', 'max' => _PS_VERSION_];

        parent::__construct();

        $this->displayName = $this->l('moofeeds');
        $this->description = $this->l('Facebook & Google Ads product feeds');
    }

    public function install()
    {
        return parent::install()
            && $this->registerHook('moduleRoutes')
            && Configuration::updateValue('MOOFEEDS_CRON_TOKEN', Tools::passwdGen(16))
            && Configuration::updateValue('MOOFEEDS_DEF_SIZE', 1000)
            && Configuration::updateValue('MOOFEEDS_DEF_STEPS', 3);
    }

    public function uninstall()
    {
        return parent::uninstall()
            && Configuration::deleteByName('MOOFEEDS_CRON_TOKEN')
            && Configuration::deleteByName('MOOFEEDS_DEF_SIZE')
            && Configuration::deleteByName('MOOFEEDS_DEF_STEPS');
    }

    public function hookModuleRoutes($params)
    {
        return [
            'module-moofeeds-facebook' => [
                'controller' => 'facebook',
                'rule' => 'feed/facebook.csv',
                'keywords' => [],
                'params' => [
                    'fc' => 'module',
                    'module' => $this->name,
                ],
            ],
            'module-moofeeds-googleads' => [
                'controller' => 'googleads',
                'rule' => 'feed/google-ads.csv',
                'keywords' => [],
                'params' => [
                    'fc' => 'module',
                    'module' => $this->name,
                ],
            ],
            'module-moofeeds-newsman' => [
                'controller' => 'newsman',
                'rule' => 'feed/newsman.csv',
                'keywords' => [],
                'params' => [
                    'fc' => 'module',
                    'module' => $this->name,
                ],
            ],
            'module-moofeeds-cron' => [
                'controller' => 'cron',
                'rule' => 'feed/cron',
                'keywords' => [],
                'params' => [
                    'fc' => 'module',
                    'module' => $this->name,
                ],
            ],
        ];
    }

    public function getContent()
    {
        $output = '';

        $adminUrl = $this->context->link->getAdminLink('AdminModules', true, [], [
            'configure' => $this->name,
            'tab_module' => $this->tab,
            'module_name' => $this->name,
        ]);

        if (Tools::isSubmit('regenerate_token')) {
            $new = Tools::passwdGen(32);
            Configuration::updateValue('MOOFEEDS_CRON_TOKEN', $new);
            Tools::redirectAdmin($adminUrl . '&moofeeds_msg=token_regen');
        }

        $token = (string) Configuration::get('MOOFEEDS_CRON_TOKEN');
        if ($token === '') {
            $token = Tools::passwdGen(32);
            Configuration::updateValue('MOOFEEDS_CRON_TOKEN', $token);
        }

        if (Tools::isSubmit('save_settings')) {
            $size = (int) Tools::getValue('def_size', 1000);
            $steps = (int) Tools::getValue('def_steps', 3);
            if ($size < 1) { $size = 1; }
            if ($steps < 1) { $steps = 1; }
            Configuration::updateValue('MOOFEEDS_DEF_SIZE', $size);
            Configuration::updateValue('MOOFEEDS_DEF_STEPS', $steps);
            Tools::redirectAdmin($adminUrl . '&moofeeds_msg=defaults_saved');
        }

        $resetMsg = '';
        if (Tools::isSubmit('reset_facebook') || Tools::isSubmit('reset_googleads') || Tools::isSubmit('reset_newsman')) {
            $feed = Tools::isSubmit('reset_facebook') ? 'facebook' : (Tools::isSubmit('reset_googleads') ? 'googleads' : 'newsman');
            $shopId = (int) $this->context->shop->id;
            $langId = (int) $this->context->language->id;
            $currency = $this->context->currency;
            $currencyIso = is_object($currency) && isset($currency->iso_code) ? $currency->iso_code : (is_string($currency) ? $currency : 'EUR');
            $base = _PS_MODULE_DIR_ . 'moofeeds/var/cache/';
            $file = sprintf('%s%s-%d-%d-%s.csv', $base, $feed, $shopId, $langId, $currencyIso);
            $tmp = $file . '.tmp';
            $stateCsv = $file . '.state.json';
            $statePlain = sprintf('%s%s-%d-%d-%s.state.json', $base, $feed, $shopId, $langId, $currencyIso);
            if (file_exists($file)) { @unlink($file); }
            if (file_exists($tmp)) { @unlink($tmp); }
            if (file_exists($stateCsv)) { @unlink($stateCsv); }
            if (file_exists($statePlain)) { @unlink($statePlain); }
            Tools::redirectAdmin($adminUrl . '&moofeeds_msg=reset_done_' . $feed);
        }

        $baseUrl = Tools::getShopDomainSsl(true) . __PS_BASE_URI__;
        $shopId = (int) $this->context->shop->id;
        $langId = (int) $this->context->language->id;
        $currency = $this->context->currency;
        $currencyIso = is_object($currency) && isset($currency->iso_code) ? $currency->iso_code : (is_string($currency) ? $currency : 'EUR');

        $stats = function ($feed) use ($shopId, $langId, $currencyIso) {
            $base = _PS_MODULE_DIR_ . 'moofeeds/var/cache/';
            if (!is_dir($base)) {
                @mkdir($base, 0775, true);
            }
            $file = sprintf('%s%s-%d-%d-%s.csv', $base, $feed, $shopId, $langId, $currencyIso);
            $stateFileLegacy = sprintf('%s%s-%d-%d-%s.csv.state.json', $base, $feed, $shopId, $langId, $currencyIso);
            $stateFilePlain = sprintf('%s%s-%d-%d-%s.state.json', $base, $feed, $shopId, $langId, $currencyIso);
            $tmpFile = $file . '.tmp';
            $data = ['exists' => false, 'mtime' => null, 'rows' => 0];
            if (is_file($file)) {
                $data['exists'] = true;
                $data['mtime'] = date('Y-m-d H:i:s', filemtime($file));
                $cnt = 0;
                $fh = fopen($file, 'r');
                if ($fh) { while (!feof($fh)) { $line = fgets($fh); if ($line !== false) { $cnt++; } } fclose($fh); }
                $data['rows'] = max(0, $cnt - 1);
                return $data;
            }
            $sf = null;
            if (is_file($stateFilePlain)) { $sf = $stateFilePlain; }
            elseif (is_file($stateFileLegacy)) { $sf = $stateFileLegacy; }
            if ($sf) {
                $data['mtime'] = date('Y-m-d H:i:s', filemtime($sf));
                $json = @file_get_contents($sf);
                if ($json) { $s = json_decode($json, true); if (is_array($s) && isset($s['processed'])) { $data['rows'] = (int) $s['processed']; } if (is_array($s) && isset($s['ts'])) { $data['mtime'] = date('Y-m-d H:i:s', (int)$s['ts']); } }
                return $data;
            }
            if (is_file($tmpFile)) { $data['mtime'] = date('Y-m-d H:i:s', filemtime($tmpFile)); }
            return $data;
        };

        $fbStats = $stats('facebook');
        $gaStats = $stats('googleads');
        $nmStats = $stats('newsman');

        $defSize = (int) Configuration::get('MOOFEEDS_DEF_SIZE');
        $defSteps = (int) Configuration::get('MOOFEEDS_DEF_STEPS');

        $msg = (string) Tools::getValue('moofeeds_msg', '');
        if ($msg === 'token_regen') { $output .= $this->displayConfirmation($this->l('Token regenerated.')); }
        elseif ($msg === 'defaults_saved') { $output .= $this->displayConfirmation($this->l('Defaults saved.')); }
        elseif ($msg === 'reset_done_facebook') { $output .= $this->displayConfirmation(sprintf($this->l('Reset done for %s'), 'facebook')); }
        elseif ($msg === 'reset_done_googleads') { $output .= $this->displayConfirmation(sprintf($this->l('Reset done for %s'), 'googleads')); }
        elseif ($msg === 'reset_done_newsman') { $output .= $this->displayConfirmation(sprintf($this->l('Reset done for %s'), 'newsman')); }

        $output .= ($resetMsg ?: '');
        $output .= '<div class="panel">';
        $output .= '<h3>' . $this->l('moofeeds settings') . '</h3>';
        $output .= '<p><strong>' . $this->l('Cron token') . ':</strong> <code>' . pSQL($token) . '</code></p>';
        $output .= '<form method="post">'
            . '<button class="btn btn-primary" name="regenerate_token" value="1">' . $this->l('Regenerate token') . '</button>'
            . '</form>';
        $output .= '<hr/>';
        $output .= '<form method="post" class="form-horizontal">';
        $output .= '<div class="form-group"><label class="control-label col-lg-3">' . $this->l('Default batch size (size)') . '</label><div class="col-lg-3"><input type="number" min="1" class="form-control" name="def_size" value="' . (int)$defSize . '"/></div></div>';
        $output .= '<div class="form-group"><label class="control-label col-lg-3">' . $this->l('Default steps per call (max_steps)') . '</label><div class="col-lg-3"><input type="number" min="1" class="form-control" name="def_steps" value="' . (int)$defSteps . '"/></div></div>';
        $output .= '<div class="form-group"><div class="col-lg-offset-3 col-lg-3"><button class="btn btn-success" name="save_settings" value="1">' . $this->l('Save defaults') . '</button></div></div>';
        $output .= '</form>';
        $output .= '<hr/>';
        $output .= '<p>' . $this->l('Cron endpoints (GET):') . '</p>';
        $output .= '<ul>';
        $output .= '<li><code>' . $baseUrl . 'feed/cron?feed=facebook&size=' . (int)$defSize . '&max_steps=' . (int)$defSteps . '&token=' . pSQL($token) . '</code></li>';
        $output .= '<li><code>' . $baseUrl . 'feed/cron?feed=googleads&size=' . (int)$defSize . '&max_steps=' . (int)$defSteps . '&token=' . pSQL($token) . '</code></li>';
        $output .= '<li><code>' . $baseUrl . 'feed/cron?feed=newsman&size=' . (int)$defSize . '&max_steps=' . (int)$defSteps . '&token=' . pSQL($token) . '</code></li>';
        $output .= '</ul>';
        $output .= '<p>' . $this->l('Feeds serve cached files if present:') . '</p>';
        $output .= '<ul>';
        $output .= '<li><code>' . $baseUrl . 'feed/facebook.csv</code></li>';
        $output .= '<li><code>' . $baseUrl . 'feed/google-ads.csv</code></li>';
        $output .= '<li><code>' . $baseUrl . 'feed/newsman.csv</code></li>';
        $output .= '</ul>';
        $output .= '<hr/>';
        $output .= '<h4>' . $this->l('Cache stats (current context)') . '</h4>';
        $output .= '<table class="table">'
            . '<thead><tr><th>Feed</th><th>' . $this->l('Exists') . '</th><th>' . $this->l('Last generated') . '</th><th>' . $this->l('Rows') . '</th><th>' . $this->l('Actions') . '</th></tr></thead>'
            . '<tbody>';
        $output .= '<tr><td>Facebook</td><td>' . ($fbStats['exists'] ? 'yes' : 'no') . '</td><td>' . ($fbStats['mtime'] ?: '-') . '</td><td>' . (int)$fbStats['rows'] . '</td><td><form method="post"><button class="btn btn-warning" name="reset_facebook" value="1">' . $this->l('Full reset') . '</button></form></td></tr>';
        $output .= '<tr><td>Google Ads</td><td>' . ($gaStats['exists'] ? 'yes' : 'no') . '</td><td>' . ($gaStats['mtime'] ?: '-') . '</td><td>' . (int)$gaStats['rows'] . '</td><td><form method="post"><button class="btn btn-warning" name="reset_googleads" value="1">' . $this->l('Full reset') . '</button></form></td></tr>';
        $output .= '<tr><td>Newsman</td><td>' . ($nmStats['exists'] ? 'yes' : 'no') . '</td><td>' . ($nmStats['mtime'] ?: '-') . '</td><td>' . (int)$nmStats['rows'] . '</td><td><form method="post"><button class="btn btn-warning" name="reset_newsman" value="1">' . $this->l('Full reset') . '</button></form></td></tr>';
        $output .= '</tbody></table>';
        $output .= '</div>';

        return $output;
    }
}
