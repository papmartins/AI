<?php

namespace App\Services;

use Rubix\ML\Datasets\Labeled;
use Rubix\ML\Datasets\Unlabeled;
use Rubix\ML\Extractors\CSV;
use Rubix\ML\Classifiers\NaiveBayes;
use Rubix\ML\Persisters\Filesystem;
use Rubix\ML\PersistentModel;

class IrisClassifier
{
    protected string $modelPath;
    protected string $datasetPath;

    public function __construct()
    {
        $this->modelPath   = storage_path('app/iris.model');
        $this->datasetPath = storage_path('app/iris.csv');
    }

    protected function train(): PersistentModel
    {
        $dataset = Labeled::fromIterator(
            new CSV($this->datasetPath, true),
            4 // coluna label
        );

        $estimator = new PersistentModel(
            new NaiveBayes(),
            new Filesystem($this->modelPath)
        );

        $estimator->train($dataset);
        $estimator->save();

        return $estimator;
    }

    protected function loadOrTrain(): PersistentModel
    {
        if (file_exists($this->modelPath)) {
            $estimator = PersistentModel::load(new Filesystem($this->modelPath));
        } else {
            $estimator = $this->train();
        }

        return $estimator;
    }

    public function predict(array $features): string
    {
        $estimator = $this->loadOrTrain();

        $dataset = new Unlabeled([
            $features,
        ]);

        $predictions = $estimator->predict($dataset);

        return $predictions[0];
    }
}
