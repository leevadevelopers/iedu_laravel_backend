<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateFormTemplateVersionsTable extends Migration
{
    public function up()
    {
        Schema::create('form_template_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_template_id')->constrained()->onDelete('cascade');
            $table->string('version_number');
            $table->text('changes_summary');
            $table->json('template_data');
            $table->unsignedBigInteger('created_by');
            $table->timestamp('created_at');
            
            $table->index(['form_template_id', 'version_number']);
            $table->foreign('created_by')->references('id')->on('users');
        });
    }

    public function down()
    {
        Schema::dropIfExists('form_template_versions');
    }
}
