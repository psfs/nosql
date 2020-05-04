app.controller('IndexCtrl', ['$scope', '$msgSrv', '$timeout', '$log',
    ($scope, $msgSrv, $timeout, $log) => {
        $scope.show = false;
        $scope.indexProperties = [];

        $scope.getIndexLegend = () => {
            let label = '-';
            try {
                label = $scope.model['name'];
            } catch(err) {
                $log.warn(err.message);
            }

            return label;
        };

        $scope.toggleIndexForm = () => {
            $scope.show = !$scope.show;
            if($scope.show) {
                $msgSrv.send('index.edit', $scope.model.id);
            }
        };

        $scope.removeIndex = () => {
            $msgSrv.send('index.remove', $scope.index);
        };

        $scope.$on('index.edit', (ev, id) => {
            if($scope.model.id !== id) {
                $scope.show = false;
            }
        });

        function prepareProperties() {
            for(let i in $scope.properties) {
                let property = $scope.properties[i];
                $scope.indexProperties.push({
                    id: property.name + '.ASC',
                    name: property.name,
                    mode: 'ASC'
                });
                $scope.indexProperties.push({
                    id: property.name + '.DESC',
                    name: property.name,
                    mode: 'DESC'
                });
            }
        }

        prepareProperties();
    }
]);
app.directive('indexes', [() => {
    return {
        restrict: 'E',
        replace: true,
        controller: 'IndexCtrl',
        templateUrl: '/js/composer/index.html',
        scope: {
            properties: '=',
            model: '=',
            index: '='
        }
    };
}]);
