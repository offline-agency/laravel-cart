<?php

declare(strict_types=1);
use Composer\InstalledVersions;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Blade;
use Laravel\Boost\Install\Agents\Codex;
use Laravel\Boost\Install\GuidelineComposer;
use Laravel\Boost\Install\GuidelineConfig;
use Laravel\Boost\Install\SkillComposer;
use Laravel\Boost\Install\SkillWriter;
use Laravel\Boost\Install\ThirdPartyPackage;
use Laravel\Roster\ProjectManager;

require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$path = __DIR__.'/vendor/offline-agency/laravel-cart/resources/boost/guidelines/core.blade.php';
$rendered = Blade::render(file_get_contents($path));
if (! str_contains($rendered, '$item = Cart::add(id: 1') || str_contains($rendered, '@verbatim')) {
    throw new RuntimeException('Installed Boost guideline did not render literal PHP safely.');
}
echo "Installed Blade guideline rendering passed.\n";
$project = app(ProjectManager::class);
$packages = ThirdPartyPackage::discover($project);
$package = $packages->get('offline-agency/laravel-cart');
if ($package === null || ! $package->hasGuidelines || ! $package->hasSkills) {
    throw new RuntimeException('Boost did not discover both third-party package resources.');
}
$config = new GuidelineConfig;
$config->aiGuidelines = ['offline-agency/laravel-cart'];
$config->hasSkills = true;
$guidelines = app(GuidelineComposer::class)->config($config)->compose();
if (! str_contains($guidelines, 'Laravel Cart') || ! str_contains($guidelines, '$item = Cart::add(id: 1')) {
    throw new RuntimeException('Boost composer did not render package guideline.');
}
$skills = app(SkillComposer::class)->config($config)->skills();
$skill = $skills->get('laravel-cart-development');
if ($skill === null || $skill->package !== 'offline-agency/laravel-cart') {
    throw new RuntimeException('Boost did not parse/discover the package skill.');
}
$writer = new SkillWriter(app(Codex::class));
if ($writer->write($skill) === SkillWriter::FAILED) {
    throw new RuntimeException('Boost failed to install the discovered skill.');
}
echo 'Boost '.InstalledVersions::getPrettyVersion('laravel/boost').": discovered, composed/rendered guidelines and installed skill.\n";
