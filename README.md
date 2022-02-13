# Commission task solution

-  run the test together with output: "composer run test"  - it displays test result and output as well.
- used and changed skeleton Math.php
- added TransactionRecord.php with TransactionRecord class
- added and used config file for commission fee rates
- output may be different if currency exchange rates at https://developers.paysera.com/tasks/api/currency-exchange-rates will change, because in automation test expected values are entered manually considering exchange rates at the time when code was written.
- "composer run test" complains about php version, because local version was 7.4 instead of 7.3