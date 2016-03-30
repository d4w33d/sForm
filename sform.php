<?php

// =============================================================================

define('sf_CONFIG_FILENAME',     'sform.xml');
define('sf_CHARSET',             'utf-8');
define('sf_DEFAULT_HTTP_METHOD', 'POST');
define('sf_DEFAULT_FIELD_TYPE',  'string');
define('sf_SESSION_KEY',         '__sform');

// -----------------------------------------------------------------------------

define('sf_DS', DIRECTORY_SEPARATOR);
define('sf_BASE_DIR', dirname(__FILE__));
define('sf_CONFIG_FILE', sf_BASE_DIR . sf_DS . sf_CONFIG_FILENAME);

// =============================================================================

class sForm
{

    // =========================================================================

    private static $instance;

    // -------------------------------------------------------------------------

    public static function setup()
    {
        self::$instance = new self();
        self::$instance->initialize();
    }

    public static function getInstance()
    {
        return self::$instance;
    }

    public static function getError($fieldName)
    {
        return self::getInstance()
            ->getSessionDataAsHtml('errors/' . $fieldName);
    }

    public static function err($fieldName, $hasError = null, $noError = null)
    {
        $error = self::getError($fieldName);

        if ($error && $hasError !== null) {
            echo $hasError;
        } else if (!$error && $noError !== null) {
            echo $noError;
        }
    }

    public static function getValue($fieldName)
    {
        return self::getInstance()
            ->getSessionDataAsHtml('values/' . $fieldName);
    }

    public static function getRawValue($fieldName)
    {
        return self::getInstance()
            ->getSessionDataAsHtml('raw/' . $fieldName);
    }

    public static function val($fieldName)
    {
        echo self::getValue($fieldName);
    }

    public static function checked($fieldName, $expectedValue = true)
    {
        $val = self::getValue($fieldName);
        if (is_bool($expectedValue)) {
            $val = (bool) self::getRawValue($fieldName);
        }

        echo $val === $expectedValue ? ' checked="checked" ' : '';
    }

    public static function selected($fieldName, $expectedValue = true)
    {
        $val = self::getValue($fieldName);
        if (is_bool($expectedValue)) {
            $val = (bool) self::getRawValue($fieldName);
        }

        echo $val === $expectedValue ? ' selected="selected" ' : '';
    }

    // =========================================================================

    private $debug = false;
    private $config;
    private $sessionData;
    private $trueValues = array('1', 'y', 'yes', 'true');

    // -------------------------------------------------------------------------

    public function initialize()
    {
        if (!session_id()) {
            session_start();
        }

        $this->config = $this->loadConfig();
        $this->debug = $this->cfg('Debug', false, true);

        if (array_key_exists(sf_SESSION_KEY, $_SESSION)) {
            $this->sessionData = $_SESSION[sf_SESSION_KEY];
            unset($_SESSION[sf_SESSION_KEY]);
        }

        if ($_SERVER['SCRIPT_FILENAME'] !== __FILE__) {
            return;
        }

        $this->execute();
    }

    private function loadConfig($xml = null, $out = array())
    {
        if ($xml === null) {
            $xml = simplexml_load_file(sf_CONFIG_FILE,
                'SimpleXMLElement',
                LIBXML_NOCDATA);
        }

        foreach ((array) $xml as $idx => $node) {
            if ($idx === 'comment') {
                continue;
            }

            $out[$idx] = is_object($node) || is_array($node)
                ? $this->loadConfig($node) : $node;
        }

        return $out;
    }

    public function cfg($key = null, $default = null, $castBool = false)
    {
        $val = $this->config;

        if ($key === null) {
            return $val;
        }

        foreach (explode('/', $key) as $col) {
            if (!array_key_exists($col, $val)) {
                return $default;
            }

            $val = $val[$col];
        }

        if ($castBool) {
            $val = in_array((string) $val, $this->trueValues);
        }

        return $val;
    }

    public function getSessionData($key)
    {
        if (!($value = $this->sessionData)) {
            return;
        }

        foreach (explode('/', $key) as $col) {
            if (!array_key_exists($col, $value)) {
                return;
            }

            $value = $value[$col];
        }

        return $value;
    }

    public function getSessionDataAsHtml($key)
    {
        if (!($val = $this->getSessionData($key))) {
            $val = '';
        }

        return $this->esc($val);
    }

    private function redirect($url)
    {
        header('Location: ' . $url);
        exit;
    }

    public function execute()
    {
        $values = array();
        $raw = array();
        $errors = array();
        $labels = array();

        foreach ($this->cfg('Fields/Field') as $field) {
            $field = $this->castFieldConfig($field);

            list($err, $rawValue, $value) = $this->getFieldValue($field);

            $values[$field['Name']] = $value;
            $raw[$field['Name']] = $rawValue;
            $labels[$field['Name']] = $field['Label'];

            if ($err !== false) {
                $errors[$field['Name']] = $err;
            }
        }

        if ($errors) {
            $_SESSION[sf_SESSION_KEY] = array(
                'values' => $values,
                'raw' => $raw,
                'errors' => $errors,
            );

            $this->redirect($this->cfg('Redirections/AfterError'));
            return;
        }

        $sysVars = array(
            'ip'   => ($ip = $_SERVER['REMOTE_ADDR']),
            'host' => @gethostbyaddr($ip),
            'date' => date('Y-m-d H:i:s'),
        );

        if ($this->cfg('Email/Enabled', false, true)) {
            $this->sendEmail($values, $sysVars, $labels);
        }

        if ($this->cfg('Log/Enabled', false, true)) {
            $this->log($values, $sysVars);
        }

        // $this->redirect($this->cfg('Redirections/AfterSuccess'));
    }

    private function sendEmail(array $values, array $sysVars, array $labels)
    {
        $cfg = $this->cfg('Email');

        // From and recipients

        foreach (array('To', 'Cc', 'Bcc') as $k) {
            $items = $cfg[$k];
            if (array_key_exists('Address', $items)) {
                $items = array($items);
            }

            $var = strtolower($k);
            $$var = [];
            foreach ($items as $item) {
                array_push($$var, $item['Name'] . ' <' . $item['Address'] . '>');
            }
        }

        $from = $cfg['From']['Name'] . ' <' . $cfg['From']['Address'] . '>';
        $replyTo = $cfg['ReplyTo']['Name'] . ' <' . $cfg['ReplyTo']['Address'] . '>';
        $to = implode(', ', $to);
        $cc = implode(', ', $cc);
        $bcc = implode(', ', $bcc);

        // Header

        $headers = '';

        $headers .= 'From: ' . $from . "\r\n";
        $headers .= 'Reply-To: ' . $replyTo . "\r\n";

        if ($cc) $headers .= 'CC: ' . $cc . "\r\n";
        if ($bcc) $headers .= 'BCC: ' . $bcc . "\r\n";

        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=" . sf_CHARSET . "\r\n";

        // Body

        $body = '';

        $body .= "<html>";
        $body .= "<body>";
        $body .= $cfg['Message'];
        $body .= "</body>";
        $body .= "</html>";

        $body = $this->replaceTag($body, '_ip', $sysVars['ip']);
        $body = $this->replaceTag($body, '_host', $sysVars['host']);
        $body = $this->replaceTag($body, '_date', $sysVars['date']);

        $htmlFields = '';

        $htmlFields .= '<table class="data">';
        $htmlFields .= '<tbody>';

        foreach ($values as $k => $v) {
            $htmlFields .= '<tr>';
            $htmlFields .= '<td><strong>' . $this->esc($labels[$k]) . '</strong></td>';
            $htmlFields .= '<td>' . $this->esc($v) . '</td>';
            $htmlFields .= '</tr>';

            $body = $this->replaceTag($body, $k, $v);
        }

        $htmlFields .= '</tbody>';
        $htmlFields .= '</table>';

        $body = $this->replaceTag($body, '_fields', $htmlFields, false);

        // Subject

        $subject = $cfg['Subject'];

        // Debug mode

        if ($this->debug) {
            header('Content-type: text/html; charset=' . sf_CHARSET);

            echo '<pre style="margin: 0 0 10px 0; '
                . 'padding: 10px; '
                . 'background: #d9edf7; '
                . 'border: 1px solid #bce8f1; '
                . 'border-radius: 3px; '
                . 'font-family: monospace; '
                . 'font-size: 12px; '
                . '">';
            echo '<strong>[DEBUG] EMAIL PREVIEW</strong><br><br>';
            echo '<strong> Subject:</strong> ' . $this->esc($subject) . '<br>';
            echo '<strong>    From:</strong> ' . $this->esc($from)    . '<br>';
            echo '<strong>Reply-To:</strong> ' . $this->esc($replyTo) . '<br>';
            echo '<strong>      To:</strong> ' . $this->esc($to)      . '<br>';
            echo '<strong>      CC:</strong> ' . $this->esc($cc)      . '<br>';
            echo '<strong>     BCC:</strong> ' . $this->esc($bcc)     . '<br>';
            echo '</pre>';

            echo $body;

            exit;
        }

        // Sending

        mail(
            $to,
            $subject,
            $body,
            $headers
        );
    }

    private function log(array $values, array $sysVars)
    {
        $data = array_merge(array(
            'form' => $values,
        ), $sysVars);

        $path = $this->cfg('Log/File');
        if (!in_array($path{0}, array('/', '\\'))) {
            $path = sf_BASE_DIR . sf_DS . $path;
        }

        @file_put_contents($path,
            json_encode($data) . "\n",
            FILE_APPEND);
    }

    private function esc($str)
    {
        return htmlentities($str, ENT_COMPAT, sf_CHARSET);
    }

    private function replaceTag($str, $tag, $replace, $escape = true)
    {
        if ($escape) {
            $replace = $this->esc($replace);
        }

        return preg_replace('/\{\{\s*' . preg_quote($tag) . '\s*\}\}/',
            $replace,
            $str);
    }

    private function castFieldConfig(array $field)
    {
        $allowedTypes = array(
            'string',
            'text',
            'boolean',
            'integer',
            'float',
        );

        $field = array_replace_recursive(array(

            'Name'       => null,
            'Label'      => null,
            'Type'       => sf_DEFAULT_FIELD_TYPE,

            'TrueLabel'  => 'Yes',
            'FalseLabel' => 'No',

            'HttpMethod' => sf_DEFAULT_HTTP_METHOD,

            'Validation' => array(
                'Mandatory'         => 'false',
                'MinLength'         => null,
                'MaxLength'         => null,
                'RegExp'            => null,
                'AllowedValues'     => null,
                'GreatThan'         => null,
                'GreatThanOrEquals' => null,
                'LessThan'          => null,
                'LessThanOrEquals'  => null,
            ),

        ), $field);

        $field['Type'] = strtolower(trim($field['Type']));
        if (!in_array($field['Type'], $allowedTypes)) {
            $field['Type'] = sf_DEFAULT_FIELD_TYPE;
        }

        $field['HttpMethod'] = strtoupper(trim($field['HttpMethod']));
        if (!in_array($field['HttpMethod'], array('GET', 'POST'))) {
            $field['HttpMethod'] = sf_DEFAULT_HTTP_METHOD;
        }

        $field['Validation']['Mandatory'] = strtolower(trim($field['Validation']['Mandatory'])) === 'true';

        foreach (array('MinLength', 'MaxLength') as $k) {
            if ($field['Validation'][$k] !== null) {
                $field['Validation'][$k] = (int) $field['Validation'][$k];
            }
        }

        foreach (array('GreatThan', 'GreatThanOrEquals', 'LessThan', 'LessThanOrEquals') as $k) {
            if ($field['Validation'][$k] !== null) {
                $field['Validation'][$k] = (float) $field['Validation'][$k];
            }
        }

        if (is_array($field['Validation']['AllowedValues'])) {
            $values = array();
            foreach ($field['Validation']['AllowedValues']['AllowedValue']  as $v) {
                $values[] = $v;
            }
            $field['Validation']['AllowedValues'] = $values;
        } else if ($field['Validation']['AllowedValues'] !== null) {
            $field['Validation']['AllowedValues'] = null;
        }

        return $field;
    }

    private function getFieldValue(array $field)
    {
        $src = $field['HttpMethod'] === 'GET' ? $_GET : $_POST;

        $value = null;
        if (array_key_exists($field['Name'], $src)) {
            $value = (string) $src[$field['Name']];
        }

        $rawValue = $value;

        if ($boolValue = in_array(strtolower($value), $this->trueValues)) {
            $boolLabel = (string) $field['TrueLabel'];
        } else {
            $boolLabel = (string) $field['FalseLabel'];
        }

        $type = $field['Type'];
        $isMandatory = $field['Validation']['Mandatory'];

        if ($isMandatory) {
            if ($type === 'boolean' && !$boolValue) {
                return array('Mandatory', $rawValue, $boolLabel);
            } else if ($value === null || $value === '') {
                return array('Mandatory', $rawValue, (string) $value);
            }
        } else if ($type !== 'boolean' && ($value === null || $value === '')) {
            return array(false, $rawValue, (string) $value);
        }

        if ($field['Validation']['AllowedValues'] !== null) {
            if (!in_array($value, $field['Validation']['AllowedValues'])) {
                return array('AllowedValues', $rawValue, (string) $value);
            }
        }

        switch ($type) {
            case 'string':
            case 'text':

                if ($type === 'text') {
                    $value = preg_replace("/[\r\n]*/", ' ', $value);
                }

                if (($c = $field['Validation']['MinLength']) !== null) {
                    if (strlen($value) < $c) {
                        return array('MinLength', $rawValue, $value);
                    }
                }

                if (($c = $field['Validation']['MaxLength']) !== null) {
                    if (strlen($value) > $c) {
                        return array('MaxLength', $rawValue, $value);
                    }
                }

                if (($c = $field['Validation']['RegExp']) !== null) {
                    if (!preg_match('/' . addslashes($c) . '/', $value)) {
                        return array('RegExp', $rawValue, $value);
                    }
                }

                break;
            case 'boolean':

                $rawValue = $boolValue;
                $value = $boolLabel;

                break;
            case 'integer':
            case 'float':

                $value = $type === 'integer' ? (int) $value : (float) $value;

                if ($field['Validation']['GreatThan'] !== null) {
                    if ($field['Validation']['GreatThan'] >= $value) {
                        return array('GreatThan', $rawValue, (string) $value);
                    }
                }

                if ($field['Validation']['GreatThanOrEquals'] !== null) {
                    if ($field['Validation']['GreatThanOrEquals'] > $value) {
                        return array('GreatThanOrEquals', $rawValue, (string) $value);
                    }
                }

                if ($field['Validation']['LessThan'] !== null) {
                    if ($field['Validation']['LessThan'] <= $value) {
                        return array('LessThan', $rawValue, (string) $value);
                    }
                }

                if ($field['Validation']['LessThanOrEquals'] !== null) {
                    if ($field['Validation']['LessThanOrEquals'] < $value) {
                        return array('LessThanOrEquals', $rawValue, (string) $value);
                    }
                }

                break;
        }

        return array(false, $rawValue, (string) $value);
    }

    // =========================================================================

}

// =============================================================================

sForm::setup();

// =============================================================================
