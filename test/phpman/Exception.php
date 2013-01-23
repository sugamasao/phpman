<?php

\phpman\Exception::clear();
eq(false,\phpman\Exception::has());
\phpman\Exception::add(new \Exception());
eq(true,\phpman\Exception::has());


\phpman\Exception::clear();
eq(false,\phpman\Exception::has());
\phpman\Exception::add(new \phpman\InvalidArgumentException());
eq(true,\phpman\Exception::has());

