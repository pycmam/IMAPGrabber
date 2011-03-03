<?php

class IMAPGrabber
{
    protected $config;
    protected $db;

    public function __construct(array $config)
    {
        $this->config = $config;

        $dsn = sprintf('mysql:host=%s;dbname=%s', $config['db']['host'], $config['db']['dbname']);

        $this->db = new PDO($dsn, $config['db']['user'], $config['db']['password']);
        $this->db->exec("SET NAMES 'utf8'");
    }

    /**
     * Сграбить почту
     */
    public function grabIt()
    {
        $mbox = sprintf('{%s:%s}', $this->config['mail']['host'], $this->config['mail']['port']);

        $imap = imap_open($mbox . $this->config['mail']['mailbox'], $this->config['mail']['username'], $this->config['mail']['password']);
        $ids = imap_search($imap, $this->config['mail']['criteria']);

        if ($ids) {
            $messages = array();
            foreach($ids as $id) {
                $overview = imap_fetch_overview($imap, $id, 0);
                $headers = imap_fetchbody($imap, $id, 0);
                $body = imap_body($imap, $id);

                // body charset
                if (preg_match('/Content-Type:\s+.*?;\s+charset=([\w\-]+)/im', $headers, $match)) {
                    $body = iconv($match[1], 'UTF-8', $body);
                }

                $messages[] = $m = array(
                    'date' => new DateTime($overview[0]->date),
                    'subject' => $overview[0]->subject,
                    'body' => $body,
                );
            }

            return $this->parse($messages);
        }
        imap_close($imap);
    }

    /**
     * Распарсить сообщения и закинуть в базу
     */
    protected function parse(array $messages)
    {
        $query = sprintf("INSERT INTO `%s` (`date`, `time`, `channel`, `number`, `msg`)".
                         " VALUES(:date, :time, :channel, :number, :msg)",
            $this->config['db']['table']);

        $sth = $this->db->prepare($query);

        $count = 0;
        foreach($messages as $msg) {
            if (preg_match('/\(\+(\d{11})\).*?channel\s+(\d)/i', $msg['subject'], $matches)) {

                $sth->execute(array(
                    ':date' => $msg['date']->format('Y-m-d'),
                    ':time' => $msg['date']->format('H:i:s'),
                    ':channel' => $matches[2],
                    ':number' => $matches[1],
                    ':msg' => $msg['body'],
                ));

                $count++;
            }
        }

        return $count;
    }
}