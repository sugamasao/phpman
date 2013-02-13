<?php

eq('http://localhost/phpman/test_index/template_abc/def',test_map_url('test_index::template_def'));
eq('http://localhost/phpman/test_index/template_abc/def/5963',test_map_url('test_index::template_def_arg1','5963'));
eq('http://localhost/phpman/test_index/template_abc/def/qaz/okm',test_map_url('test_index::template_def_arg2','qaz','okm'));
