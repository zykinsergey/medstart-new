<?php

require_once dirname(__FILE__)."/lib/class.phpmailer.php";
require_once dirname(__FILE__)."/lib/class.amoapi.php";

ignore_user_abort(true);

/**
 * Class Lead
 * @property PHPMailer $mailer
 */


class Lead {
    private $mailer;
    private $counter;

    public $phone_db = array();

    private $lead_name;
    private $lead_email;
    private $lead_phone;
    private $lead_additional;

    private $msg_title;
    private $msg_body;

    private $setting_project_name = 'Премиальные авточехлы из экокожи от производителя';

    private $setting_amo_subdomain = 'amo_subdomain';
    private $setting_amo_login = 'amo_login';
    private $setting_amo_hash = 'amo_hash';
    private $setting_amo_field_contact_phone = 213215;
    private $setting_amo_field_contact_email = 213217;
    private $setting_amo_responsible_user_id = 777307;

    private $integration_status_sms = '';
    private $integration_status_amo = '';

    public function __construct()
    {
        $this->prepareIntegration();
        $this->preparePost();
    }

    private function prepareIntegration()
    {
        $mailer = new PHPMailer();
        $mailer->CharSet = 'UTF-8';

        $mailer->setFrom('no-reply@'.$_SERVER['HTTP_HOST'], $this->setting_project_name);

//        $mailer->addAddress('hpptv@yandex.ru');
        $mailer->addAddress('zykinsergey@gmail.com');

        $counter = @file_get_contents(dirname(__FILE__).'/counter.txt');
        $counter += 1;
        file_put_contents(dirname(__FILE__).'/counter.txt',$counter);


        $this->mailer = $mailer;
        $this->counter = $counter;
    }

    private function preparePost()
    {
        $this->lead_name = @$_POST['name'] ? $_POST['name'] : null;
        $this->lead_phone = @$_POST['phone'] ? $_POST['phone'] : null;
        $this->lead_email = @$_POST['email'] ? $_POST['email'] : null;
        
        if (@$_POST['whencall'])
            $this->lead_additional .= '<b>Когда позвонить:</b> '.nl2br($_POST['whencall']).'<br>';

        if (@$_POST['whencall'] == 'По времени'  && @$_POST['whentime'])
            $this->lead_additional .= '<b>Удобное время звонка:</b> '.nl2br($_POST['whentime']).'<br>';

        if (@$_POST['marks'])
            $this->lead_additional .= '<b>Марка: </b> '.nl2br($_POST['marks']).'<br>';

        if (@$_POST['models'])
            $this->lead_additional .= '<b>Модель: </b> '.nl2br($_POST['models']).'<br>';

        if (@$_POST['version'])
            $this->lead_additional .= '<b>Версия:</b> '.nl2br($_POST['version']).'<br>';

        if (@$_POST['additional'])
            $this->lead_additional .= '<b>Дополнительно:</b> '.nl2br($_POST['additional']).'<br>';

        if (@$_POST['additional2'])
            $this->lead_additional .= '<b>Дополнительно:</b> '.nl2br($_POST['additional2']).'<br>';

        if (@$_POST['utm_type'])
            $this->lead_additional .= '<br><b>utm_type:</b> '.nl2br($_POST['utm_type']).'<br>';
        if (@$_POST['utm_source'])
            $this->lead_additional .= '<b>utm_source:</b> '.nl2br($_POST['utm_source']).'<br>';
        if (@$_POST['utm_medium'])
            $this->lead_additional .= '<b>utm_medium:</b> '.nl2br($_POST['utm_medium']).'<br>';
        if (@$_POST['utm_campaign'])
            $this->lead_additional .= '<b>utm_campaign:</b> '.nl2br($_POST['utm_campaign']).'<br>';
        if (@$_POST['utm_term'])
            $this->lead_additional .= '<b>utm_term:</b> '.nl2br($_POST['utm_term']).'<br>';
        if (@$_POST['utm_content'])
            $this->lead_additional .= '<b>utm_content:</b> '.nl2br($_POST['utm_content']).'<br>';


        $this->msg_title = $this->setting_project_name.'. Заявка №' . $this->counter;
        if (@$_POST['version'])
            $this->msg_title .= ' ['.($_POST['version']).'] ';
    }

    public function sendToAmo()
    {
        $amo = new AmoAPI($this->setting_amo_subdomain, $this->setting_amo_login, $this->setting_amo_hash);
        $amo->CUSTOM_FIELD_CONTACT_PHONE = $this->setting_amo_field_contact_phone;
        $amo->CUSTOM_FIELD_CONTACT_EMAIL = $this->setting_amo_field_contact_email;

        $contact_name = $this->lead_name ? $this->lead_name : $this->lead_phone;

        try {
            $responsible_user = $this->setting_amo_responsible_user_id;

            $contact = $amo->contactsSearchByPhone($this->lead_phone);

            if (empty($contact) || !@$contact['linked_leads_id'])
            {
                $amo_lead_info = $amo->leadAdd(
                    $this->msg_title,
                    $responsible_user
                );

                $amo_lead_id = null;
                if (is_array($amo_lead_info) && isset($amo_lead_info['leads']['add'][0]))
                    $amo_lead_id = $amo_lead_info['leads']['add'][0]['id'];

                $amo->contactAdd($contact_name, $this->lead_email,'',$this->lead_phone,$amo_lead_id, $responsible_user);
                $amo->taskAdd($amo_lead_id, AmoAPI::TASK_ELEMENT_TYPE_LEAD, AmoAPI::TASK_TYPE_CALL,
                    'Позвонить', $responsible_user, time()+600);

                if ($amo_lead_id)
                {
                    $msg = $this->strip_tags_br2nl( $this->lead_additional.$this->integration_status_sms );
                    $amo->noteAddForLead($amo_lead_id, $msg);
                }
                $this->integration_status_amo = 'Заявка успешно добавлена в Amo.CRM <br><br>
                        <a href="https://'.$this->setting_amo_subdomain.'.amocrm.ru/leads/detail/'.$amo_lead_id.'" target="_blank">Перейти к сделке в Amo.CRM</a>
                        ';
            }
            else
            {
                $leads = $contact['linked_leads_id'];
                $lead_id = @$leads[0];
                if ($lead_id) {
                    $this->msg_title = 'Повторная '.$this->msg_title;
                    $msg = $this->msg_title. PHP_EOL;
                    $msg .= $this->strip_tags_br2nl( $this->lead_additional.$this->integration_status_sms );
                    $amo->noteAddForLead($lead_id, $msg);
                    $this->integration_status_amo = 'Контакт уже существует. Сделка уже существует. Добавлено примечание к сделке. <br><br>
                            <a href="https://'.$this->setting_amo_subdomain.'.amocrm.ru/leads/detail/'.$lead_id.'" target="_blank">Перейти к сделке в Amo.CRM</a>
                            ';
                    $amo->taskAdd($contact['id'], AmoAPI::TASK_ELEMENT_TYPE_CONTACT, AmoApi::TASK_TYPE_CALL,
                        'Позвонить. Повторный звонок.', $responsible_user, time()+600);
                }
            }

        } catch (Exception $e) {
            $this->integration_status_amo = 'Ошибка интеграции с Amo.CRM. '.$e->getMessage();
        }
    }

    public function sendMail()
    {
        $title = $this->msg_title;
        $message = "<h4>" . $title . "</h4>";
        if ($this->lead_name)
            $message .= sprintf("<b>Имя:</b> %s <br />", $this->lead_name);
        if ($this->lead_email)
            $message .= sprintf("<b>E-mail:</b> %s <br />", $this->lead_email);
        if ($this->lead_phone)
            $message .= sprintf("<b>Телефон:</b> %s <br />", $this->lead_phone);
        if ($this->lead_additional)
            $message .= $this->lead_additional;
//        if ($this->integration_status_sms)
//            $message .= $this->integration_status_sms;
//        if ($this->integration_status_amo)
//            $message .= '<b>Статус интеграции с Amo:</b> '.$this->integration_status_amo;

        if (isset($_FILES['userfile'])) {
            $this->mailer->AddAttachment($_FILES['userfile']['tmp_name'],
                $_FILES['uploaded_file']['name']);
        }


        $this->mailer->isHTML(true);

        $this->mailer->Subject = $title;
        $this->mailer->Body    = $message;

        if(!$this->mailer->send()) {
            echo 'Message could not be sent.';
            echo 'Mailer Error: ' . $this->mailer->ErrorInfo;
        } else {
            echo 'Message has been sent';
        }

        $headers  = 'MIME-Version: 1.0' . "\r\n";
        $headers .= 'Content-type: text/html; charset=UTF-8' . "\r\n";
        $headers .= 'From: hpptv@gmail.com <hpptv@yandex.ru>' . "\r\n";

//        mail("zaborstroy43@gmail.com", $title, $message, $headers);


    }

    private function strip_tags_br2nl($msg)
    {
        return strip_tags( str_replace(array('<br />', '<br>'),"\n",$msg) );
    }
}


$lead = new Lead();

//$lead->sendToAmo();
$lead->sendMail();
echo 'ok';
