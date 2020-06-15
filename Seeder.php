<?php

require 'vendor/autoload.php';
use Symfony\Component\Dotenv\Dotenv;

class Seeder
{
    private $DB_HOST;
    private $DB_NAME;
    private $DB_USER;
    private $DB_PASSWORD;
    private $DUMP_FILE;

    private function getEnv()
    {
        $dotenv = new Dotenv();
        $dotenv->load(__DIR__.'/.env');

        $this->DB_HOST = $_ENV['DB_HOST'];
        $this->DB_NAME = $_ENV['DB_NAME'];
        $this->DB_USER = $_ENV['DB_USER'];
        $this->DB_PASSWORD = $_ENV['DB_PASSWORD'];
        $this->DUMP_FILE = $_ENV['DUMP_FILE'];
    }

    private function getCountDataFromCSV()
    {
        return count(file($this->DUMP_FILE));
    }

    private function getItemFromCSV($number)
    {
        return file($this->DUMP_FILE)[$number - 1];
    }

    private function writeToDatabase($item)
    {
        try {
            $dbh = new PDO('mysql:host='.$this->DB_HOST.';dbname='.$this->DB_NAME, $this->DB_USER, $this->DB_PASSWORD);
            
            if (gettype($item) == 'array') {
                $params = $item;
            } else {
                $params = str_getcsv($item);
            }

            $stmt_s = $dbh->prepare('SELECT * FROM users WHERE email = ?');
            $stmt_s->execute([$params[2]]);
            $user = $stmt_s->fetch();

            if ($user) {
                $stmt_b = $dbh->prepare('SELECT * FROM transactions WHERE user_id = :user_id AND type = :type LIMIT 1');
                $stmt_b->bindParam(':user_id', $user_id);
                $stmt_b->bindParam(':type', $type);
                $user_id = $user['id'];
            
                $type = 'INFLOW_CREATE';
                $stmt_b->execute();
                $balans = $stmt_b->fetch();

                $stmt_sb = $dbh->prepare('SELECT * FROM transactions WHERE user_id = :user_id AND type = :type LIMIT 1');
                $stmt_sb->bindParam(':user_id', $user_id);
                $stmt_sb->bindParam(':type', $type);
                $user_id = $user['id'];

                $type = 'OUTFLOW_ORDER';
                $stmt_sb->execute();
                $sumbalans = $stmt_sb->fetch();

                if ($balans['amount'] < $params[3]) {
                    $stmt_du = $dbh->prepare('DELETE FROM users WHERE id = ?');
                    $stmt_du->execute([$user['id']]);

                    $stmt_dts = $dbh->prepare('DELETE FROM transactions WHERE user_id = ?');
                    $stmt_dts->execute([$user['id']]);

                    $stmt_u = $dbh->prepare('INSERT INTO users (name, password, email, premium_status_id) VALUES (:name, :password, :email, :premium_status_id)');

                    $stmt_u->bindParam(':name', $name);
                    $stmt_u->bindParam(':password', $password);
                    $stmt_u->bindParam(':email', $email);
                    $stmt_u->bindParam(':premium_status_id', $premium_status_id);

                    $name = trim($params[0]);
                    $password = trim($params[1]);
                    $email = trim($params[2]);
                    if ($params[13] < 5000) {
                        $premium_status_id = 1;
                    } else if ($params[13] >= 5000 && $params[13] < 25000) {
                        $premium_status_id = 2;
                    } else if ($params[13] >= 25000 && $params[13] < 50000) {
                        $premium_status_id = 3;
                    } else if ($params[13] >= 50000 && $params[13] < 100000) {
                        $premium_status_id = 4;
                    } else if ($params[13] >= 100000) {
                        $premium_status_id = 5;
                    }

                    $stmt_u->execute();

                    $user_id = $dbh->lastInsertId();

                    $stmt_t = $dbh->prepare('INSERT INTO transactions (user_id, event_id, type, amount, comment, created_at, updated_at, related_user_id, commission) values (:user_id, :event_id, :type, :amount, :comment, NOW(), NOW(), NULL, 0)');

                    $stmt_t->bindParam(':user_id', $user_id);
                    $stmt_t->bindParam(':event_id', $uniq_id);
                    $stmt_t->bindParam(':type', $type);
                    $stmt_t->bindParam(':amount', $amount);
                    $stmt_t->bindParam(':comment', $comment);

                    $uniq_id = uniqid();
                    $type = 'INFLOW_CREATE';
                    $amount = $params[3];
                    $comment = 'Пользовательские средства на предыдущей платформе';

                    $stmt_t->execute();

                    $uniq_id = uniqid();
                    $type = 'INFLOW_PAYMENT';
                    $amount = $params[13];
                    $comment = 'Списанные средства';

                    $stmt_t->execute();

                    $uniq_id = uniqid();
                    $type = 'OUTFLOW_ORDER';
                    $amount = -$params[13];
                    $comment = 'Сумма заказов на предыдущей платформе';

                    $stmt_t->execute();

                    echo $user_id .' | User '. $name .' migrated'."\n";
                } else if ($balans['amount'] == $params[3] && -$sumbalans['amount'] > $params[13]) {
                    $stmt_du = $dbh->prepare('DELETE FROM users WHERE id = ?');
                    $stmt_du->execute([$user['id']]);

                    $stmt_dts = $dbh->prepare('DELETE FROM transactions WHERE user_id = ?');
                    $stmt_dts->execute([$user['id']]);

                    $stmt_u = $dbh->prepare('INSERT INTO users (name, password, email, premium_status_id) VALUES (:name, :password, :email, :premium_status_id)');

                    $stmt_u->bindParam(':name', $name);
                    $stmt_u->bindParam(':password', $password);
                    $stmt_u->bindParam(':email', $email);
                    $stmt_u->bindParam(':premium_status_id', $premium_status_id);

                    $name = trim($params[0]);
                    $password = trim($params[1]);
                    $email = trim($params[2]);
                    if ($params[13] < 5000) {
                        $premium_status_id = 1;
                    } else if ($params[13] >= 5000 && $params[13] < 25000) {
                        $premium_status_id = 2;
                    } else if ($params[13] >= 25000 && $params[13] < 50000) {
                        $premium_status_id = 3;
                    } else if ($params[13] >= 50000 && $params[13] < 100000) {
                        $premium_status_id = 4;
                    } else if ($params[13] >= 100000) {
                        $premium_status_id = 5;
                    }

                    $stmt_u->execute();

                    $user_id = $dbh->lastInsertId();

                    $stmt_t = $dbh->prepare('INSERT INTO transactions (user_id, event_id, type, amount, comment, created_at, updated_at, related_user_id, commission) values (:user_id, :event_id, :type, :amount, :comment, NOW(), NOW(), NULL, 0)');

                    $stmt_t->bindParam(':user_id', $user_id);
                    $stmt_t->bindParam(':event_id', $uniq_id);
                    $stmt_t->bindParam(':type', $type);
                    $stmt_t->bindParam(':amount', $amount);
                    $stmt_t->bindParam(':comment', $comment);

                    $uniq_id = uniqid();
                    $type = 'INFLOW_CREATE';
                    $amount = $params[3];
                    $comment = 'Пользовательские средства на предыдущей платформе';

                    $stmt_t->execute();

                    $uniq_id = uniqid();
                    $type = 'INFLOW_PAYMENT';
                    $amount = $params[13];
                    $comment = 'Списанные средства';

                    $stmt_t->execute();

                    $uniq_id = uniqid();
                    $type = 'OUTFLOW_ORDER';
                    $amount = -$params[13];
                    $comment = 'Сумма заказов на предыдущей платформе';

                    $stmt_t->execute();

                    echo $user_id .' | User '. $name .' migrated'."\n";
                }
            } else {
                $stmt_u = $dbh->prepare('INSERT INTO users (name, password, email, premium_status_id) VALUES (:name, :password, :email, :premium_status_id)');

                $stmt_u->bindParam(':name', $name);
                $stmt_u->bindParam(':password', $password);
                $stmt_u->bindParam(':email', $email);
                $stmt_u->bindParam(':premium_status_id', $premium_status_id);

                $name = trim($params[0]);
                $password = trim($params[1]);
                $email = trim($params[2]);
                if ($params[13] < 5000) {
                    $premium_status_id = 1;
                } else if ($params[13] >= 5000 && $params[13] < 25000) {
                    $premium_status_id = 2;
                } else if ($params[13] >= 25000 && $params[13] < 50000) {
                    $premium_status_id = 3;
                } else if ($params[13] >= 50000 && $params[13] < 100000) {
                    $premium_status_id = 4;
                } else if ($params[13] >= 100000) {
                    $premium_status_id = 5;
                }

                $stmt_u->execute();

                $user_id = $dbh->lastInsertId();

                $stmt_t = $dbh->prepare('INSERT INTO transactions (user_id, event_id, type, amount, comment, created_at, updated_at, related_user_id, commission) values (:user_id, :event_id, :type, :amount, :comment, NOW(), NOW(), NULL, 0)');

                $stmt_t->bindParam(':user_id', $user_id);
                $stmt_t->bindParam(':event_id', $uniq_id);
                $stmt_t->bindParam(':type', $type);
                $stmt_t->bindParam(':amount', $amount);
                $stmt_t->bindParam(':comment', $comment);

                $uniq_id = uniqid();
                $type = 'INFLOW_CREATE';
                $amount = $params[3];
                $comment = 'Пользовательские средства на предыдущей платформе';

                $stmt_t->execute();

                $uniq_id = uniqid();
                $type = 'INFLOW_PAYMENT';
                $amount = $params[13];
                $comment = 'Списанные средства';

                $stmt_t->execute();

                $uniq_id = uniqid();
                $type = 'OUTFLOW_ORDER';
                $amount = -$params[13];
                $comment = 'Сумма заказов на предыдущей платформе';

                $stmt_t->execute();

                echo $user_id .' | User '. $name .' migrated'."\n";
            }
        } catch (PDOException $e) {
            echo 'Error!: ' . $e->getMessage();
            die();
        }
    }

    function __construct()
    {
        $this->getEnv();

        for ($i = 1; $i <= $this->getCountDataFromCSV(); $i++) {
            $this->writeToDatabase($this->getItemFromCSV($i));
        }
    }
}