<?php

namespace Database\Seeders;

use App\Models\DeviceType;
use App\Models\OldText;
use App\Models\UiText;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class UiTextSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $dTypes = DeviceType::get();
        foreach($dTypes as $dType){
            $tableName = (new $dType->device_type)->getTable();
            $textTableName = $tableName."_text_template";
            if(Schema::hasTable($tableName)){
                $oldText = new OldText();
                $texts = $oldText->setTable($textTableName)->get()->keyBy('text_id');

                foreach($texts as $key => $text){
                    if($key < 2000 && $text->text){
                        $deKey = 2000 + $key;
                        $textKey = strtolower($text->text);
                        $textKey =preg_replace('/\s+/', '_', $textKey);
                        UiText::updateOrCreate(
                            [
                                "device_type_id" => $dType->id,
                                "prev_id" => $key
                            ],
                            [
                                "text_en" => $text->text,
                                "text_de" => $texts[$deKey]->text,
                                "text_key" => $textKey
                            ]
                        );
                    }
                }
            }
        }
    }
}
