<?php

namespace Alexantr\HootkiGrosh;

/**
 * HootkiGrosh Class
 * @author Alex Yashkin <alex@yashkin.by>
 * @link https://github.com/alexantr/HootkiGrosh
 */
class HootkiGrosh
{
    /**
     * @var string
     */
    protected static $cookies_file;

    /**
     * @var string URL API
     */
    protected $base_url;

    /**
     * @var resource cURL handle
     */
    protected $ch;

    /**
     * @var string Ошибка запроса (если есть)
     */
    protected $error;

    /**
     * @var string Тело ответа
     */
    protected $response;

    /**
     * @var int Код статуса
     */
    protected $status;

    /**
     * @var string Временная папка с файлами cookies
     */
    protected $cookies_dir;

    /**
     * @var string Рабочий api url
     */
    protected $api_url = 'https://www.hutkigrosh.by/API/v1/';

    /**
     * @var string Тестовый api url
     */
    protected $test_api_url = 'https://trial.hgrosh.by/API/v1/';

    /**
     * @var array Список ошибок
     */
    protected $status_error = array(
        '3221291009' => 'Общая ошибка сервиса',
        '3221291521' => 'Нет информации о счете',
        '3221291522' => 'Нет возможности удалить счет',
        '3221291523' => 'Общая ошибка выставления счета',
        '3221291524' => 'Не указан номер счета',
        '3221291525' => 'Счет не уникальный',
        '3221291526' => 'Счет уже выставлен, но срок оплаты прошел',
        '3221291527' => 'Счет выставлен и оплачен',
        '3221291528' => 'Не указано количество товаров/услуг в заказе',
        '3221291529' => 'Не указана сумма счета',
        '3221291530' => 'Не указано наименование товара',
        '3221291531' => 'Общая сумма счета меньше нуля',
        '3221291601' => 'Возвращены не все счета',
        '3221292033' => 'Общая ошибка установки курсов валют',
        '3221292034' => 'Не указан коэффициент к курсу НБ РБ',
        '3221292035' => 'Не определены курсы валют поставщика услуг',
        '3221292036' => 'Не установлен режим пересчета курсов валют',
        '3221292289' => 'Общая ошибка при получении курсов валют',
        '3221292801' => 'В запросе отсутствуют параметры',
        '3221292802' => 'Поле billId не заполнен',
        '3221292803' => 'Нет url для перенаправления в случае успешной оплаты',
        '3221292804' => 'Нет url для перенаправления в случае ошибки или отмены оплаты',
        '3221292806' => 'Счет не найден в системе',
        '3221292807' => 'Поставщик услуг не найден в системе',
        '3221292808' => 'Услуга не полностью сконфигурирована',
        '3221292809' => 'Нет номера услуги на платежном сайте в настройках',
        '3221292810' => 'Счет оплачен ранее',
    );

    /**
     * @var array Список статусов счета
     */
    protected $purch_item_status = array(
        'NotSet' => 'Не установлено',
        'PaymentPending' => 'Ожидание оплаты',
        'Outstending' => 'Просроченный',
        'DeletedByUser' => 'Удален',
        'PaymentCancelled' => 'Прерван',
        'Payed' => 'Оплачен',
    );

    /**
     * @var array Доступные валюты
     */
    protected $currencies = array('BYN', 'USD', 'EUR', 'RUB');

    /**
     * @var string Валюта по умолчанию (для нового счета)
     */
    protected $default_currency = 'BYN';

    /**
     * @var string Текст для неизвестного статуса счета
     */
    protected $unknown_status_text = 'Статус не определен';

    /**
     * @var string Текст для ошибки неверного ответа сервера
     */
    protected $response_error_text = 'Неверный ответ сервера';

    /**
     * @var string Текст для неизвестного статуса ошибки ответа
     */
    protected $unknown_status_error_text = 'Неизвестная ошибка';

    /**
     * @param bool $is_test Использовать ли тестовый api
     */
    public function __construct($is_test = false)
    {
        if ($is_test) {
            $this->base_url = $this->test_api_url;
        } else {
            $this->base_url = $this->api_url;
        }

        if (!isset(self::$cookies_file)) {
            self::$cookies_file = 'hg-cookies-' . time() . '.txt';
        }

        $this->setCookiesDir(sys_get_temp_dir());
    }

    /**
     * Задать путь к папке, где будет находиться файл cookies
     * @param string $dir
     */
    public function setCookiesDir($dir)
    {
        $dir = rtrim($dir, '\\/');
        if (is_dir($dir)) {
            $this->cookies_dir = $dir;
        } else {
            $this->cookies_dir = sys_get_temp_dir();
        }
    }

    /**
     * Аутентифицирует пользователя в системе
     * @param string $user
     * @param string $pwd
     * @return bool
     */
    public function apiLogIn($user, $pwd)
    {
        // формируем xml
        $Credentials = new \SimpleXMLElement('<Credentials></Credentials>');
        $Credentials->addAttribute('xmlns', 'http://www.hutkigrosh.by/api');
        $Credentials->addChild('user', trim($user));
        $Credentials->addChild('pwd', trim($pwd));

        $xml = $Credentials->asXML();

        // запрос
        $res = $this->requestPost('Security/LogIn', $xml);

        // проверим, верны ли логин/пароль
        if ($res && !preg_match('/true/', $this->response)) {
            $this->error = 'Ошибка авторизации';
            return false;
        }

        return $res;
    }

    /**
     * Завершает сессию
     * @return bool
     */
    public function apiLogOut()
    {
        $res = $this->requestPost('Security/LogOut');
        // удалим файл с cookies
        $cookies_path = $this->cookies_dir . DIRECTORY_SEPARATOR . self::$cookies_file;
        if (is_file($cookies_path)) {
            @unlink($cookies_path);
        }
        return $res;
    }

    /**
     * Добавляет новый счет в систему
     * @param array $data
     * @return bool|string
     */
    public function apiBillNew($data)
    {
        // выберем валюту
        $curr = isset($data['curr']) ? trim($data['curr']) : $this->default_currency;
        if (!in_array($curr, $this->currencies)) {
            $curr = $this->currencies[0];
        }

        // формируем xml
        $Bill = new \SimpleXMLElement('<Bill></Bill>');
        $Bill->addAttribute('xmlns', 'http://www.hutkigrosh.by/api/invoicing');
        $Bill->addChild('eripId', trim($data['eripId']));
        $Bill->addChild('invId', trim($data['invId']));
        $Bill->addChild('dueDt', date('c', strtotime('+1 day'))); // +1 день
        $Bill->addChild('addedDt', date('c'));
        $Bill->addChild('fullName', trim($data['fullName']));
        $Bill->addChild('mobilePhone', trim($data['mobilePhone']));
        $Bill->addChild('notifyByMobilePhone', 'false');
        if (isset($data['email'])) {
            $Bill->addChild('email', trim($data['email'])); // опционально
        }
        $Bill->addChild('notifyByEMail', 'false');
        if (isset($data['fullAddress'])) {
            $Bill->addChild('fullAddress', trim($data['fullAddress'])); // опционально
        }
        if (isset($data['amt'])) {
            $Bill->addChild('amt', (float)$data['amt']); // опционально
        }
        $Bill->addChild('curr', $curr);
        $Bill->addChild('statusEnum', 'NotSet');
        // Список товаров/услуг
        if (isset($data['products']) && !empty($data['products'])) {
            $products = $Bill->addChild('products');
            foreach ($data['products'] as $pr) {
                $ProductInfo = $products->addChild('ProductInfo');
                if (isset($pr['invItemId'])) {
                    $ProductInfo->addChild('invItemId', trim($pr['invItemId'])); // опционально
                }
                $ProductInfo->addChild('desc', trim($pr['desc']));
                $ProductInfo->addChild('count', (int)$pr['count']);
                if (isset($pr['amt'])) {
                    $ProductInfo->addChild('amt', (float)$pr['amt']); // опционально
                }
            }
        }

        $xml = $Bill->asXML();

        // запрос
        $res = $this->requestPost('Invoicing/Bill', $xml);

        if ($res) {
            $array = $this->responseToArray();

            if (is_array($array) && isset($array['status']) && isset($array['billID'])) {
                $this->status = (int)$array['status'];
                $billID = trim("{$array['billID']}");

                // есть ошибка
                if ($this->status > 0) {
                    $this->error = $this->getStatusError($this->status);
                    return false;
                }

                return $billID;
            } else {
                $this->error = $this->response_error_text;
            }
        }

        return false;
    }

    /**
     * Извлекает информацию о выставленном счете
     * @param string $bill_id
     * @return bool|array
     */
    public function apiBillInfo($bill_id)
    {
        // запрос
        $res = $this->requestGet("Invoicing/Bill({$bill_id})");

        if ($res) {
            $array = $this->responseToArray();

            if (is_array($array) && isset($array['status']) && isset($array['bill'])) {
                $this->status = (int)$array['status'];
                $bill = (array)$array['bill'];

                // есть ошибка
                if ($this->status > 0) {
                    $this->error = $this->getStatusError($this->status);
                    return false;
                }

                return $bill;
            } else {
                $this->error = $this->response_error_text;
            }
        }

        return false;
    }

    /**
     * Удаляет выставленный счет из системы
     * @param string $bill_id
     * @return bool|mixed
     */
    public function apiBillDelete($bill_id)
    {
        $res = $this->requestDelete("Invoicing/Bill({$bill_id})");

        if ($res) {
            $array = $this->responseToArray();

            if (is_array($array) && isset($array['status']) && isset($array['purchItemStatus'])) {
                $this->status = (int)$array['status'];
                $purchItemStatus = trim($array['purchItemStatus']); // статус счета

                // есть ошибка
                if ($this->status > 0) {
                    $this->error = $this->getStatusError($this->status);
                    return false;
                }

                return $purchItemStatus;
            } else {
                $this->error = $this->response_error_text;
            }
        }

        return false;
    }

    /**
     * Возвращает статус указанного счета
     * @param string $bill_id
     * @return bool|mixed
     */
    public function apiBillStatus($bill_id)
    {
        $res = $this->requestGet("Invoicing/BillStatus({$bill_id})");

        if ($res) {
            $array = $this->responseToArray();

            if (is_array($array) && isset($array['status']) && isset($array['purchItemStatus'])) {
                $this->status = (int)$array['status'];
                $purchItemStatus = trim($array['purchItemStatus']); // статус счета

                // есть ошибка
                if ($this->status > 0) {
                    $this->error = $this->getStatusError($this->status);
                    return false;
                }

                return $purchItemStatus;
            } else {
                $this->error = $this->response_error_text;
            }
        }

        return false;
    }

    /**
     * Получение списка всех платежей
     * @param int $erip_id
     * @param string $last_bill_id
     * @return bool|array
     */
    public function apiPayedBills($erip_id, $last_bill_id)
    {
        // запрос
        $res = $this->requestGet("Invoicing/PayedBills({$erip_id},{$last_bill_id})");

        if ($res) {
            $array = $this->responseToArray();

            if (is_array($array) && isset($array['status']) && isset($array['bill'])) {
                $this->status = (int)$array['status'];
                $bills = (array)$array['bill'];

                // есть ошибка
                if ($this->status > 0) {
                    $this->error = $this->getStatusError($this->status);
                    return false;
                }

                return $bills;
            } else {
                $this->error = $this->response_error_text;
            }
        }

        return false;
    }

    /**
     * Получить текст ошибки
     * @return string
     */
    public function getError()
    {
        return $this->error;
    }

    /**
     * Ответ сервера в исходном виде
     * @return mixed
     */
    public function getResponse()
    {
        return $this->response;
    }

    /**
     * Статус ответа
     * @return mixed
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * Статус счета
     * @param string $status
     * @return string
     */
    public function getPurchItemStatus($status)
    {
        return (isset($this->purch_item_status[$status])) ? $this->purch_item_status[$status] : $this->unknown_status_text;
    }

    /**
     * Подключение GET
     * @param string $path
     * @param string $data
     * @return bool
     */
    protected function requestGet($path, $data = '')
    {
        return $this->connect($path, $data, 'GET');
    }

    /**
     * Подключение POST
     * @param string $path
     * @param string $data
     * @return bool
     */
    protected function requestPost($path, $data = '')
    {
        return $this->connect($path, $data, 'POST');
    }

    /**
     * Подключение DELETE
     * @param string $path
     * @param string $data
     * @return bool
     */
    protected function requestDelete($path, $data = '')
    {
        return $this->connect($path, $data, 'DELETE');
    }

    /**
     * Подключение GET, POST или DELETE
     * @param string $path
     * @param string $data Сформированный для отправки XML
     * @param string $request
     * @return bool
     */
    protected function connect($path, $data = '', $request = 'GET')
    {
        $headers = array(
            'Content-Type: application/xml',
            'Content-Length: ' . (function_exists('mb_strlen') ? mb_strlen($data, '8bit') : strlen($data)),
        );

        $this->ch = curl_init();

        curl_setopt($this->ch, CURLOPT_URL, $this->base_url . $path);
        curl_setopt($this->ch, CURLOPT_HEADER, false);
        curl_setopt($this->ch, CURLOPT_VERBOSE, false);
        curl_setopt($this->ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($this->ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($this->ch, CURLOPT_HTTPHEADER, $headers);
        if ($request == 'POST') {
            curl_setopt($this->ch, CURLOPT_POST, true);
            curl_setopt($this->ch, CURLOPT_POSTFIELDS, $data);
        }
        if ($request == 'DELETE') {
            curl_setopt($this->ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        }

        $cookies_path = $this->cookies_dir . DIRECTORY_SEPARATOR . self::$cookies_file;

        // если файла еще нет, то создадим его при залогинивании и будем затем использовать при дальнейших запросах
        if (!is_file($cookies_path)) {
            curl_setopt($this->ch, CURLOPT_COOKIEJAR, $cookies_path);
        }
        curl_setopt($this->ch, CURLOPT_COOKIEFILE, $cookies_path);

        $this->response = curl_exec($this->ch);

        if (curl_errno($this->ch)) {
            $this->error = curl_error($this->ch);
            curl_close($this->ch);
            return false;
        } else {
            curl_close($this->ch);
            return true;
        }
    }

    /**
     * Преобразуем XML в массив
     * @return mixed
     */
    protected function responseToArray()
    {
        $response = trim($this->response);
        $array = array();
        // проверим, что это xml
        if (preg_match('/^<(.*)>$/', $response)) {
            $xml = simplexml_load_string($response);
            $array = json_decode(json_encode($xml), true);
        }
        return $array;
    }

    /**
     * Описание ошибки на основе ее кода в ответе
     * @param string $status
     * @return string
     */
    protected function getStatusError($status)
    {
        return (isset($this->status_error[$status])) ? $this->status_error[$status] : $this->unknown_status_error_text;
    }
}
