<?php

declare(strict_types=1);

use OfflineAgency\LaravelCart\Tests\CartAssertions;
use OfflineAgency\LaravelCart\Tests\FeatureTestCase;

uses(FeatureTestCase::class, CartAssertions::class)->in('.');
