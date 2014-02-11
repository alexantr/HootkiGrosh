<?php

/**
 * Class HootkiGrosh
 * @author Alex Yashkin (alexantr) <alex.yashkin@gmail.com>
 */
class HootkiGrosh
{
	private static $cookies_file; // имя файла с cookies

	private $base_url; // url api

	private $ch; // curl object
	private $error; // ошибка запроса (если есть)
	private $response; // тело ответа

	// Список ошибок
	private $status_error = array(
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
	);

	// Список статусов счета
	private $purch_item_status = array(
		'NotSet'           => 'Не установлено',
		'PaymentPending'   => 'Ожидание оплаты',
		'Outstending'      => 'Просроченный',
		'DeletedByUser'    => 'Удален',
		'PaymentCancelled' => 'Прерван',
		'Payed'            => 'Оплачен',
	);

	/**
	 * @param bool $is_test Использовать ли тестовый api
	 */
	public function __construct($is_test = false)
	{
		if ($is_test) {
			$this->base_url = 'https://trial.hgrosh.by/API/v1/';
		}
		else {
			$this->base_url = 'https://www.hutkigrosh.by/API/v1/';
		}

		if (!isset(self::$cookies_file)) {
			self::$cookies_file = 'cookies-' . time() . '.txt';
		}
	}

	/**
	 * Аутентифицирует пользователя в системе
	 *
	 * @param string $user
	 * @param string $pwd
	 *
	 * @return bool
	 */
	public function apiLogIn($user, $pwd)
	{
		// формируем xml
		$Credentials = new SimpleXMLElement("<Credentials></Credentials>");
		$Credentials->addAttribute('xmlns', 'http://www.hutkigrosh.by/api');
		$Credentials->addChild('user', trim($user));
		$Credentials->addChild('pwd', trim($pwd));

		$xml = $Credentials->asXML();

		// запрос
		$res = $this->_requestPost('Security/LogIn', $xml);

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
		$res = $this->_requestPost('Security/LogOut');
		// удалим файл с cookies
		if (is_file(self::$cookies_file)) {
			@unlink(self::$cookies_file);
		}
		return $res;
	}

	/**
	 * Добавляет новый счет в систему
	 *
	 * @param array $data
	 *
	 * @return bool|string
	 */
	public function apiBillNew($data)
	{
		// формируем xml
		$Bill = new SimpleXMLElement("<Bill></Bill>");
		$Bill->addAttribute('xmlns', 'http://www.hutkigrosh.by/api/invoicing');
		$Bill->addChild('eripId', trim($data['eripId']));
		$Bill->addChild('invId', trim($data['invId']));
		$Bill->addChild('dueDt', date('c', strtotime('+1 day'))); // +1 день
		$Bill->addChild('addedDt', date('c'));
		$Bill->addChild('fullName', trim($data['fullName']));
		$Bill->addChild('mobilePhone', trim($data['mobilePhone']));
		$Bill->addChild('notifyByMobilePhone', 'false');
		if (isset($data['email']))
			$Bill->addChild('email', trim($data['email'])); // опционально
		$Bill->addChild('notifyByEMail', 'false');
		if (isset($data['fullAddress']))
			$Bill->addChild('fullAddress', trim($data['fullAddress'])); // опционально
		if (isset($data['amt']))
			$Bill->addChild('amt', (float)$data['amt']); // опционально
		$Bill->addChild('curr', 'BYR');
		$Bill->addChild('statusEnum', 'NotSet');
		// Список товаров/услуг
		if (isset($data['products']) && !empty($data['products'])) {
			$products = $Bill->addChild('products');
			foreach ($data['products'] as $pr) {
				$ProductInfo = $products->addChild('ProductInfo');
				if (isset($pr['invItemId']))
					$ProductInfo->addChild('invItemId', trim($pr['invItemId'])); // опционально
				$ProductInfo->addChild('desc', trim($pr['desc']));
				$ProductInfo->addChild('count', (int)$pr['count']);
				if (isset($pr['amt']))
					$ProductInfo->addChild('amt', (float)$pr['amt']); // опционально
			}
		}

		$xml = $Bill->asXML();

		// запрос
		$res = $this->_requestPost('Invoicing/Bill', $xml);

		if ($res) {
			$array = $this->_responseToArray();

			if (is_array($array) && isset($array['status']) && isset($array['billID'])) {
				$status = (int)$array['status'];
				$billID = trim("{$array['billID']}");

				// есть ошибка
				if ($status > 0) {
					$this->error = $this->_getStatusError($status);
					return false;
				}

				return $billID;
			}
			else {
				$this->error = 'Неверный ответ сервера';
			}
		}

		return false;
	}

	/**
	 * Извлекает информацию о выставленном счете
	 *
	 * @param string $bill_id
	 *
	 * @return bool|array
	 */
	public function apiBillInfo($bill_id)
	{
		// запрос
		$res = $this->_requestGet('Invoicing/Bill(' . $bill_id . ')');

		if ($res) {
			$array = $this->_responseToArray();

			if (is_array($array) && isset($array['status']) && isset($array['bill'])) {
				$status = (int)$array['status'];
				$bill = (array)$array['bill'];

				// есть ошибка
				if ($status > 0) {
					$this->error = $this->_getStatusError($status);
					return false;
				}

				return $bill;
			}
			else {
				$this->error = 'Неверный ответ сервера';
			}
		}

		return false;
	}

	/**
	 * Удаляет выставленный счет из системы
	 *
	 * @param string $bill_id
	 *
	 * @return bool|mixed
	 */
	public function apiBillDelete($bill_id)
	{
		$res = $this->_requestDelete('Invoicing/Bill(' . $bill_id . ')');

		if ($res) {
			$array = $this->_responseToArray();

			if (is_array($array) && isset($array['status']) && isset($array['purchItemStatus'])) {
				$status = (int)$array['status'];
				$purchItemStatus = trim($array['purchItemStatus']); // статус счета

				// есть ошибка
				if ($status > 0) {
					$this->error = $this->_getStatusError($status);
					return false;
				}

				return $purchItemStatus;
			}
			else {
				$this->error = 'Неверный ответ сервера';
			}
		}

		return false;
	}

	/**
	 * Возвращает статус указанного счета
	 *
	 * @param string $bill_id
	 *
	 * @return bool|mixed
	 */
	public function apiBillStatus($bill_id)
	{
		$res = $this->_requestGet('Invoicing/BillStatus(' . $bill_id . ')');

		if ($res) {
			$array = $this->_responseToArray();

			if (is_array($array) && isset($array['status']) && isset($array['purchItemStatus'])) {
				$status = (int)$array['status'];
				$purchItemStatus = trim($array['purchItemStatus']); // статус счета

				// есть ошибка
				if ($status > 0) {
					$this->error = $this->_getStatusError($status);
					return false;
				}

				return $purchItemStatus;
			}
			else {
				$this->error = 'Неверный ответ сервера';
			}
		}

		return false;
	}

	/**
	 * Получить текст ошибки
	 *
	 * @return string
	 */
	public function getError()
	{
		return $this->error;
	}

	/**
	 * Ответ сервера в исходном виде
	 *
	 * @return mixed
	 */
	public function getResponse()
	{
		return $this->response;
	}

	/**
	 * Статус счета
	 *
	 * @param string $status
	 *
	 * @return string
	 */
	public function getPurchItemStatus($status)
	{
		return (isset($this->purch_item_status[$status])) ? $this->purch_item_status[$status] : 'Статус не определен';
	}

	/**
	 * Подключение GET
	 *
	 * @param string $path
	 * @param string $data
	 *
	 * @return bool
	 */
	private function _requestGet($path, $data = '')
	{
		return $this->_connect($path, $data, 'GET');
	}

	/**
	 * Подключение POST
	 *
	 * @param string $path
	 * @param string $data
	 *
	 * @return bool
	 */
	private function _requestPost($path, $data = '')
	{
		return $this->_connect($path, $data, 'POST');
	}

	/**
	 * Подключение DELETE
	 *
	 * @param string $path
	 * @param string $data
	 *
	 * @return bool
	 */
	private function _requestDelete($path, $data = '')
	{
		return $this->_connect($path, $data, 'DELETE');
	}

	/**
	 * Подключение GET, POST или DELETE
	 *
	 * @param string $path
	 * @param string $data     Сформированный для отправки XML
	 * @param string $request
	 *
	 * @return bool
	 */
	private function _connect($path, $data = '', $request = 'GET')
	{
		$headers = array('Content-Type: application/xml', 'Content-Length: ' . strlen($data));

		$this->ch = curl_init();

		curl_setopt($this->ch, CURLOPT_URL, $this->base_url . $path);
		curl_setopt($this->ch, CURLOPT_HEADER, false); // включение заголовков в выводе
		curl_setopt($this->ch, CURLOPT_VERBOSE, true); // вывод доп. информации в STDERR
		curl_setopt($this->ch, CURLOPT_SSL_VERIFYPEER, false); // не проверять сертификат узла сети
		curl_setopt($this->ch, CURLOPT_SSL_VERIFYHOST, false); // проверка существования общего имени в сертификате SSL
		curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, true); // возврат результата вместо вывода на экран
		curl_setopt($this->ch, CURLOPT_HTTPHEADER, $headers); // Массив устанавливаемых HTTP-заголовков
		if ($request == 'POST') {
			curl_setopt($this->ch, CURLOPT_POST, true);
			curl_setopt($this->ch, CURLOPT_POSTFIELDS, $data);
		}
		if ($request == 'DELETE') {
			curl_setopt($this->ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
		}

		// если файла еще нет, то создадим его при залогинивании и будем затем использовать при дальнейших запросах
		if (!is_file(self::$cookies_file)) {
			curl_setopt($this->ch, CURLOPT_COOKIEJAR, self::$cookies_file);
		}
		curl_setopt($this->ch, CURLOPT_COOKIEFILE, self::$cookies_file);

		$this->response = curl_exec($this->ch);

		if (curl_errno($this->ch)) {
			$this->error = curl_error($this->ch);
			curl_close($this->ch);
			return false;
		}
		else {
			curl_close($this->ch);
			return true;
		}
	}

	/**
	 * Преобразуем XML в массив
	 *
	 * @return mixed
	 */
	private function _responseToArray()
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
	 *
	 * @param string $status
	 *
	 * @return string
	 */
	private function _getStatusError($status)
	{
		return (isset($this->status_error[$status])) ? $this->status_error[$status] : 'Неизвестная ошибка';
	}

}
