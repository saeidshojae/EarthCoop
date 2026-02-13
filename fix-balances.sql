UPDATE najm_sub_accounts SET balance = balance_active + balance_faded, status = 1 WHERE id > 0;
UPDATE najm_accounts SET balance = balance_active + balance_faded WHERE id > 0;
