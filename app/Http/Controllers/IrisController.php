<?php

namespace App\Http\Controllers;

use App\Services\IrisClassifier;
use Illuminate\Http\Request;

class IrisController extends Controller
{
    public function predict(Request $request, IrisClassifier $classifier)
    {
        $data = $request->validate([
            'sepal_length' => 'required|numeric',
            'sepal_width'  => 'required|numeric',
            'petal_length' => 'required|numeric',
            'petal_width'  => 'required|numeric',
        ]);

        $features = [
            $data['sepal_length'],
            $data['sepal_width'],
            $data['petal_length'],
            $data['petal_width'],
        ];

        $prediction = $classifier->predict($features);

        return response()->json([
            'input'      => $features,
            'prediction' => $prediction,
        ]
        );
    }
}
