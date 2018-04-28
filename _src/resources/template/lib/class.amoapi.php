<?php

class AmoAPI
{
    private $subdomain = null;
    private $login = null;
    private $password = null;
    private $hash = null;

    private $cookieAuthError = false;
    private $lastError = null;
    private $lastErrorNo = null;

    // API URLS
    const API_URL_AUTH = '/private/api/auth.php';
    const API_URL_CURRENT_ACCOUNT = '/private/api/v2/json/accounts/current';
    const API_URL_CONTACTS_LIST = '/private/api/v2/json/contacts/list';
    const API_URL_CONTACTS_SET = '/private/api/v2/json/contacts/set';
    const API_URL_LEADS_LIST = '/private/api/v2/json/leads/list';
    const API_URL_LEADS_SET = '/private/api/v2/json/leads/set';
    const API_URL_NOTES_LIST = '/private/api/v2/json/notes/list';
    const API_URL_NOTES_SET = '/private/api/v2/json/notes/set';
    const API_URL_TASKS_SET = '/private/api/v2/json/tasks/set';

    // CUSTOM FIELDS
    public $CUSTOM_FIELD_CONTACT_PHONE = 1153068;
    public $CUSTOM_FIELD_CONTACT_EMAIL = 1153070;

    // NOTE TYPE
    const NOTE_ELEMENT_TYPE_CONTACT = 1;
    const NOTE_ELEMENT_TYPE_LEAD = 2;

    // TASKS

    const TASK_ELEMENT_TYPE_CONTACT = 1;
    const TASK_ELEMENT_TYPE_LEAD = 2;

    const TASK_TYPE_CALL = 'CALL';
    const TASK_TYPE_LETTER = 'LETTER';
    const TASK_TYPE_MEETING = 'MEETING';

    private $curl_cookie_path = './cookie.txt';

    public function __construct($subdomain, $login, $hash = null, $password = null)
    {
        $this->curl_cookie_path = 'amo_cookie.txt';

        $this->subdomain = $subdomain;
        $this->login = $login;
        $this->hash = $hash;
        $this->password = $password;

        $this->auth();
    }

    /**
     * Авторизация
     *
     * @throws Exception
     */

    public function auth()
    {
        $params = array();
        $params['USER_LOGIN'] = $this->login;

        if ($this->hash) {
            $params['USER_HASH'] = $this->hash;
        } elseif ($this->password) {
            $params['USER_PASSWORD'] = $this->password;
        } else {
            throw new Exception('User Password or Hash are required to authorize.');
        }

        if (!$this->cookieAuthError)
            if ( file_exists( $this->curl_cookie_path ))
                return;

        $this->call(self::API_URL_AUTH, $params, array('type'=>'json'));
    }

    /**
     * Получение информации об аккаунте
     *
     * @return array
     */
    public function currentAccount() {
        $this->account = $this->call(self::API_URL_CURRENT_ACCOUNT, null, null, 'GET');
        return $this->account;
    }

    /**
     * Получение страницы со списком контактов
     *
     * @param null|integer $limit_rows Номер страницы
     * @param null|integer $limit_offset Количество выданных элементов
     * @param null|array $additional_params Дополнительные параметры
     * @return mixed
     * @throws Exception
     */
    public function contactsList($limit_rows = 500, $limit_offset = null, $additional_params = null)
    {
        $params = array();

        if ($limit_rows)
            $params['limit_rows'] = $limit_rows;

        if ($limit_rows && $limit_offset)
            $params['limit_offset'] = $limit_offset;

        if ($additional_params && !empty($additional_params) && is_array($additional_params))
            $params = array_merge($params,$additional_params);

        return $this->call(self::API_URL_CONTACTS_LIST, null, $params, 'GET');
    }

    /**
     * Получение информации по ID контакта
     *
     * @param integer $id ID контакта
     * @return mixed
     * @throws Exception
     */
    public function contactsSearchByID($id)
    {
        $params = array(
            'id' => (array)$id
        );

        return $this->contactsList(null, null, $params);
    }

    /**
     * Поиск контактов
     *
     * @param string $keyword Искомое слово
     * @param null|integer $limit_rows Количество выданных элементов
     * @param null|integer $limit_offset Оффсет
     * @return mixed
     * @throws Exception
     */
    public function contactsSearchByKeyword($keyword, $limit_rows = 500, $limit_offset = null)
    {
        $params = array(
            'query' => $keyword
        );

        if ($limit_rows)
            $params['limit_rows'] = $limit_rows;

        if ($limit_rows && $limit_offset)
            $params['limit_offset'] = $limit_offset;

        return $this->contactsList(null, null, $params);
    }

    /**
     * Поиск контактов
     *
     * @param string $keyword Искомое слово
     * @param null|integer $limit_rows Количество выданных элементов
     * @param null|integer $limit_offset Оффсет
     * @return mixed
     * @throws Exception
     */
    public function contactsSearchByPhone($search_phone)
    {
        $params = array(
            'query' => $search_phone
        );

        $contacts = $this->contactsList(null, null, $params);

        if (empty($contacts))
            return null;

        foreach($contacts['contacts'] as $contact)
        {
            foreach($contact['custom_fields'] as $field)
            {
                if ($field['code'] == 'PHONE')
                {
                    foreach($field['values'] as $phone)
                    {
                        if ($phone['value'] == $search_phone)
                            return $contact;
                    }
                }
            }
        }
        return null;
    }

    /**
     * Поиск контактов по ответственному юзеру
     *
     * @param string $keyword Искомое слово
     * @param null|integer $limit_rows Количество выданных элементов
     * @param null|integer $limit_offset Оффсет
     * @return mixed
     * @throws Exception
     */
    public function contactsSearchByResponsibleUserID($keyword, $limit_rows = 500, $limit_offset = null)
    {
        $params = array(
            'query' => $keyword
        );

        if ($limit_rows)
            $params['limit_rows'] = $limit_rows;

        if ($limit_rows && $limit_offset)
            $params['limit_offset'] = $limit_offset;

        return $this->contactsList(null, null, $params);
    }


    /**
     * Добавление контакта
     *
     * @return mixed
     * @throws Exception
     */
    public function contactAdd($name, $email, $company = null, $phone = null, $lead_id = null, $responsible_user_id = null)
    {
        return $this->contactUpdate(null, $name, $email, $company, $phone, $lead_id, $responsible_user_id);
    }

    /**
     * Редактирование контакта в amoCRM
     *
     * @param integer $id ID контакта
     * @return mixed
     * @throws Exception
     */
    public function contactUpdate($id, $name, $email, $company = null, $phone = null, $lead_id = null, $responsible_user_id = null)
    {
        if ($id)
            $contact = $this->contactsSearchByID($id);
        else
            $contact = $this->contactsSearchByPhone($phone);


        if (!empty($contact))
        {
            $is_update = true;
        }
        else
        {
            $contact = array();
            $is_update = false;
        }

        $contact['name'] = $name;
        $contact['linked_leads_id'] = $is_update ? array_merge( @$contact['linked_leads_id'], array($lead_id)) : array($lead_id);
        $contact['custom_fields'] = array();

        if ($responsible_user_id)
            $contact['responsible_user_id'] = $responsible_user_id;

        if ($email)
            $contact['custom_fields'][] =  array(
                'id'=>$this->CUSTOM_FIELD_CONTACT_EMAIL,
                'values'=>array(
                    array(
                        'value'=>$email,
                        'enum'=>'WORK'
                    )
                )
            );

        if ($company)
            $contact['company_name'] = $company;

        if ($phone)
            $contact['custom_fields'][]=array(
                'id'=>$this->CUSTOM_FIELD_CONTACT_PHONE,
                'values'=>array(
                    array(
                        'value'=>$phone,
                        'enum'=>'OTHER'
                    )
                )
            );

        if ($is_update)
            $contact['last_modified'] = time();

        $params = array(
            'contacts' => array(
                $is_update ? 'update' : 'add' => array(
                    $contact
                )
            )
        );

        return $this->call(self::API_URL_CONTACTS_SET, $params, null, 'JSON');
    }


    /**
     * Удаление контакта из amoCRM
     *
     * @param integer $id ID контакта
     * @return mixed
     * @throws Exception
     */
    /*
    public function deleteContact($id)
    {
        $params = array(
            'ID' => $id,
            'ACTION' => 'DELETE'
        );

        return $this->call('/private/api/contact_delete.php', $params);
    }
    */

    /**
     * Получение страницы со списком сделок
     *
     * @param null|integer $limit_rows Номер страницы
     * @param null|integer $limit_offset Количество выданных элементов
     * @param null|array $additional_params Дополнительные параметры
     * @return mixed
     * @throws Exception
     */
    public function leadsList($limit_rows = 500, $limit_offset = null, $additional_params = null)
    {
        $params = array();

        if ($limit_rows)
            $params['limit_rows'] = $limit_rows;

        if ($limit_rows && $limit_offset)
            $params['limit_offset'] = $limit_offset;

        if ($additional_params && !empty($additional_params) && is_array($additional_params))
            $params = array_merge($params,$additional_params);

        return $this->call(self::API_URL_LEADS_LIST, null, $params, 'GET');
    }

    /**
     * Получение информации по ID сделки
     *
     * @param integer $id ID контакта
     * @return mixed
     * @throws Exception
     */
    public function leadSearchByID($id)
    {
        $params = array(
            'id' => (array)$id
        );

        return $this->leadsList(null, null, $params);
    }

    /**
     * Поиск сделок
     *
     * @param string $keyword Искомое слово
     * @param null|integer $limit_rows Количество выданных элементов
     * @param null|integer $limit_offset Оффсет
     * @return mixed
     * @throws Exception
     */
    public function leadsSearchByKeyword($keyword, $limit_rows = 500, $limit_offset = null)
    {
        $params = array(
            'query' => $keyword
        );

        if ($limit_rows)
            $params['limit_rows'] = $limit_rows;

        if ($limit_rows && $limit_offset)
            $params['limit_offset'] = $limit_offset;

        return $this->leadsList(null, null, $params);
    }

    /**
     * Поиск сделок по ответственному юзеру
     *
     * @param string $keyword Искомое слово
     * @param null|integer $limit_rows Количество выданных элементов
     * @param null|integer $limit_offset Оффсет
     * @return mixed
     * @throws Exception
     */
    public function leadsSearchByResponsibleUserID($keyword, $limit_rows = 500, $limit_offset = null)
    {
        $params = array(
            'query' => $keyword
        );

        if ($limit_rows)
            $params['limit_rows'] = $limit_rows;

        if ($limit_rows && $limit_offset)
            $params['limit_offset'] = $limit_offset;

        return $this->leadsList(null, null, $params);
    }


    /**
     * Добавление сделки
     *
     * @return mixed
     * @throws Exception
     */
    public function leadAdd($name, $responsible_user_id = null, $price = null, $tags = null, $custom_fields = array(), $status_id = null)
    {
        return $this->leadUpdate(null, $name, $responsible_user_id, $price, $tags, $custom_fields, $status_id);
    }

    public function leadUpdate($id, $name, $responsible_user_id = null, $price = null, $tags = null, $custom_fields = array(), $status_id = null)
    {
        if ($id)
            $lead = $this->leadSearchByID($id);
        else
            $lead = null;

        if ($lead && !empty($lead['leads']))
            $lead = $lead['leads'][0];

        $is_update = (bool)$lead;

        if ($is_update)
        {
            if ($name) $lead['name'] = $name;
            if ($responsible_user_id) $lead['responsible_user_id'] = $responsible_user_id;
            if ($price) $lead['price'] = $price;
            if ($tags) $lead['tags'] = $tags;
            if ($status_id) $lead['status_id'] = $status_id;
        }
        else
        {
            $lead = array(
                'name' => $name,
                'price' => $price,
                'custom_fields' => $custom_fields,
                'tags' => $tags,
                'responsible_user_id' => $responsible_user_id,
                'status_id' => $status_id
            );
        }


        if ($is_update)
            $lead['last_modified'] = time();

        $params = array(
            'leads' => array(
                $is_update ? 'update' : 'add' => array(
                    $lead
                )
            )
        );

        return $this->call(self::API_URL_LEADS_SET, $params, null, 'JSON');
    }

    /**
     * Удаление сделки из amoCRM
     *
     * @param integer $id ID контакта
     * @return mixed
     * @throws Exception
     */
    /*public function deleteDeal($id)
    {
        $params = array(
            'ID' => $id,
            'ACTION' => 'DELETE'
        );

        return $this->call('/private/api/deal_delete.php', $params);
    }
    */

    /**
     * Добавление задачи
     *
     * @param integer $elementID ID контакта
     * @param $message Текст примечания
     * @return mixed
     * @throws Exception
     */

    public function taskAdd($element_id, $element_type, $task_type, $text, $responsible_user_id, $complete_till) {
        $task = array(
            'element_id' => $element_id,
            'element_type'=> $element_type,
            'task_type' => $task_type,
            'text'      => $text,
            'responsible_user_id' => $responsible_user_id,
            'complete_till'       => $complete_till
        );

        $params = array(
            'tasks' => array(
                'add' => array(
                    $task
                )
            )
        );

        return $this->call(self::API_URL_TASKS_SET, $params, null, 'JSON');
    }

    /**
     * Добавление примечания к контакту
     *
     * @param integer $elementID ID контакта
     * @param $message Текст примечания
     * @return mixed
     * @throws Exception
     */

    public function noteAddForContact($elementID, $message) {
        $params['notes']['add']=array(
            array(
                'element_id' => $elementID,
                'element_type' => self::NOTE_ELEMENT_TYPE_CONTACT,
                'note_type' => 4,
                'text' => $message
            )
        );

        return $this->call(self::API_URL_NOTES_SET, $params, null, 'JSON');
    }

    /**
     * Добавление примечания к сделке
     *
     * @param integer $elementID ID контакта
     * @param $message Текст примечания
     * @return mixed
     * @throws Exception
     */

    public function noteAddForLead($elementID, $message) {
        $params['notes']['add']=array(
            array(
                'element_id' => $elementID,
                'element_type' => self::NOTE_ELEMENT_TYPE_LEAD,
                'note_type' => 4,
                'text' => $message
            )
        );

        return $this->call(self::API_URL_NOTES_SET, $params, null, 'JSON');
    }


    /**
     * Обращение к API amoCRM
     *
     * @param string $url_path
     * @param array $post_params
     * @param array $get_params
     * @param string $request_method
     * @return mixed
     * @throws Exception
     */
    private function call($url_path, $post_params = array(), $get_params = array(), $request_method = 'POST')
    {
        $this->lastError = null;

        $url = 'https://' . $this->subdomain . '.amocrm.ru' . $url_path;

        if (!empty($get_params))
            $url.='?'.http_build_query($get_params,'','&');

        $ch = curl_init();

        if ($request_method == 'GET')
        {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $request_method);
        }
        elseif ($request_method == 'POST')
        {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $request_method);
            curl_setopt($ch, CURLOPT_POST, true);
            if ($post_params)
                curl_setopt($ch, CURLOPT_POSTFIELDS, $post_params);
        }
        elseif ($request_method == 'JSON')
        {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
            if ($post_params)
            {
                curl_setopt($ch,CURLOPT_HTTPHEADER,array('Content-Type: application/json'));
                curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode(array( 'request' => $post_params ) ));
            }
        }

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch,CURLOPT_COOKIEFILE, $this->curl_cookie_path);
        curl_setopt($ch,CURLOPT_COOKIEJAR, $this->curl_cookie_path);
        $result = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);


        switch ($code) {
            case '201':
                $this->lastError = 'Добавление контактов: пустой массив';
                break;
            case '202':
                $this->lastError = 'Добавление контактов: нет прав';
                break;
            case '203':
                $this->lastError = 'Добавление контактов: системная ошибка при работе с дополнительными полями';
                break;
            case '204':
                $this->lastError = 'Добавление контактов: дополнительное поле не найдено';
                break;
            case '205':
                $this->lastError = 'Добавление контактов: контакт не создан';
                break;
            case '206':
                $this->lastError = 'Добавление/Обновление контактов: пустой запрос';
                break;
            case '207':
                $this->lastError = 'Добавление/Обновление контактов: неверный запрашиваемый метод';
                break;
            case '208':
                $this->lastError = 'Обновление контактов: пустой массив';
                break;
            case '209':
                $this->lastError = 'Обновление контактов: требуются параметры "id" и "last_modified"';
                break;
            case '210':
                $this->lastError = 'Обновление контактов: системная ошибка при работе с дополнительными полями';
                break;
            case '211':
                $this->lastError = 'Обновление контактов: дополнительное поле не найдено';
                break;
            case '212':
                $this->lastError = 'Обновление контактов: контакт не обновлён';
                break;

            case '301':
                $this->lastError = 'Ошибка. Запрошенный документ был окончательно перенесен.';
                break;
            case '400':
                $this->lastError = 'Ошибка. Сервер обнаружил в запросе клиента синтаксическую ошибку.';
                break;
            case '401':
                $this->lastError = 'Ошибка. Запрос требует идентификации пользователя.';
                break;
            case '403':
                $this->lastError = 'Ошибка. Ограничение в доступе к указанному ресурсу.';
                break;
            case '404':
                $this->lastError = 'Ошибка. Страница не найдена.';
                break;
            case '500':
                $this->lastError = 'Внутрення ошибка сервера.';
                break;
            case '502':
                $this->lastError = 'Ошибка. Неудачное выполнение.';
                break;
            case '503':
                $this->lastError = 'Ошибка. Сервер временно недоступен.';
                break;
            default:
                $this->lastError = 'Ошибка. Пожалуйста, проверьте введённые данные. Код ошибки: '.$code;
        }

        if (!in_array($code,array(200,204))) {
            $this->lastErrorNo = $code;

            if ($code == 401 && !$this->cookieAuthError)
            {
                $this->cookieAuthError = true;
                $this->auth();

                return $this->call($url_path, $post_params, $get_params, $request_method);
            }

            throw new Exception($this->lastError, $this->lastErrorNo);
        }

        if (!$result)
            return null;
        $result = json_decode($result, true);

        return $result['response'];
    }
}