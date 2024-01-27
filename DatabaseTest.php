<?php

namespace FpDbTest;

use Exception;

class DatabaseTest
{
    private DatabaseInterface $db;

    public function __construct(DatabaseInterface $db)
    {
        $this->db = $db;
    }

    public function testBuildQuery(): void
    {
        $results = [];

        $results[] = $this->db->buildQuery('SELECT name FROM users WHERE user_id = 1');

        $results[] = $this->db->buildQuery(
            'SELECT * FROM users WHERE name = ? AND block = 0',
            ['Jack']
        );

        $results[] = $this->db->buildQuery(
            'SELECT ?# FROM users WHERE user_id = ?d AND block = ?d',
            [['name', 'email'], 2, true]
        );

        $results[] = $this->db->buildQuery(
            'UPDATE users SET ?a WHERE user_id = -1',
            [['name' => 'Jack', 'email' => null]]
        );

        foreach ([null, true] as $block) {
            $results[] = $this->db->buildQuery(
                'SELECT name FROM users WHERE ?# IN (?a){ AND block = ?d}',
                ['user_id', [1, 2, 3], $block ?? $this->db->skip()]
            );
        }

        $correct = [
            'SELECT name FROM users WHERE user_id = 1',
            'SELECT * FROM users WHERE name = \'Jack\' AND block = 0',
            'SELECT `name`, `email` FROM users WHERE user_id = 2 AND block = 1',
            'UPDATE users SET `name` = \'Jack\', `email` = NULL WHERE user_id = -1',
            'SELECT name FROM users WHERE `user_id` IN (1, 2, 3)',
            'SELECT name FROM users WHERE `user_id` IN (1, 2, 3) AND block = 1',
        ];

        if ($results !== $correct) {
            throw new Exception('Failure.');
        }
        
        //дополнительно от себя
        $results = [];

        $correct = [
            'UPDATE users SET `name` = \'Jack\', `email` = NULL WHERE `pass` = \'pass\'',
            'UPDATE users SET `name` = \'Jack\', `email` = NULL WHERE `role_id` = 5',
            'UPDATE users SET `order_id` = 5, `email` = NULL WHERE user_id = -1',
            'SELECT name FROM users WHERE `user_id` IN (1, 2, 3)',
            'SELECT name FROM users WHERE `block` = 1',
            'SELECT name FROM users WHERE `block` = 1 limit 1',
        ];

        $results[] = $this->db->buildQuery(
            'UPDATE users SET ?a WHERE ?a',
            [['name' => 'Jack', 'email' => null], ['pass' => 'pass']]
        );

        $results[] = $this->db->buildQuery(
            'UPDATE users SET ?a WHERE ?a',
            [['name' => 'Jack', 'email' => null], ['role_id' => 5]]
        );

        $results[] = $this->db->buildQuery(
            'UPDATE users SET ?a WHERE user_id = -1',
            [['order_id' => 5, 'email' => null]]
        );
        foreach ([true] as $block) {
            $results[] = $this->db->buildQuery(
                'SELECT name FROM users WHERE ?# IN (?a) {{AND block = ?d}}',
                ['user_id', [1, 2, 3], $block]
            );
        }
        foreach ([true] as $block) {
            $results[] = $this->db->buildQuery(
                'SELECT name FROM users WHERE ?# = ?d {{AND block = ?d}}',
                ['block', $block, $block]
            );
        }

        foreach ([true] as $block) {
            $results[] = $this->db->buildQuery(
                'SELECT name FROM users WHERE ?# = ?d {{AND block = ?d} {AND block = ?d}} limit ?d',
                ['block', $block, $block, $block, 1]
            );
        }

        
        if ($results !== $correct) {
            throw new Exception('Failure.');
        }
    }
}
