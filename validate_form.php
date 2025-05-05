<?php
function validate_form($post_data) {
    $errors = [];

    if (!preg_match('/^[a-zA-Zа-яА-Я\s]{1,150}$/u', $post_data['fio'])) {
        $errors['fio'] = 'ФИО: только буквы и пробелы, до 150 символов';
    }

    if (!preg_match('/^\+?[1-9]\d{1,14}$/', $post_data['phone'])) {
        $errors['phone'] = 'Телефон: только цифры и +, пример: +79991234567';
    }

    if (!preg_match('/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/', $post_data['email'])) {
        $errors['email'] = 'Email: неверный формат, пример: user@example.com';
    }

    $birthdate = new DateTime($post_data['birthdate']);
    $now = new DateTime();
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $post_data['birthdate']) || $birthdate >= $now) {
        $errors['birthdate'] = 'Дата рождения: должна быть в прошлом в формате ГГГГ-ММ-ДД';
    }

    if (!preg_match('/^(male|female)$/', $post_data['gender'])) {
        $errors['gender'] = 'Пол: выберите мужской или женский';
    }

    $valid_languages = range(1, 12);
    if (empty($post_data['languages'])) {
        $errors['languages'] = 'Языки: выберите хотя бы один';
    } else {
        foreach ($post_data['languages'] as $lang) {
            if (!in_array($lang, $valid_languages)) {
                $errors['languages'] = 'Языки: неверное значение';
                break;
            }
        }
    }

    if (!preg_match('/^[\w\sа-яА-Я.,!?-]{1,1000}$/u', $post_data['bio'])) {
        $errors['bio'] = 'Биография: буквы, цифры, пробелы и знаки .,!?- до 1000 символов';
    }

    if (!isset($post_data['contract'])) {
        $errors['contract'] = 'Контракт: необходимо согласие';
    }

    return $errors;
}
?>