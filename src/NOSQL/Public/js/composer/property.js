app.controller('PropertyCtrl', ['$scope', '$msgSrv', '$timeout', '$log',
    ($scope, $msgSrv, $timeout, $log) => {
        $scope.show = false;

        $scope.getPropertyLegend = () => {
            let label = '-';
            try {
                label = $scope.model['name'];
                if($scope.model.type) {
                    label += ' (' + $scope.model.type + ')';
                }
            } catch(err) {
                $log.warn(err.message);
            }

            return label;
        };

        $scope.togglePropertyForm = () => {
            $scope.show = !$scope.show;
            if($scope.show) {
                $msgSrv.send('property.edit', $scope.model.id);
            }
        };

        $scope.removeProperty = () => {
            $msgSrv.send('property.remove', $scope.index);
        };

        $scope.$on('property.edit', (ev, id) => {
            if($scope.model.id !== id) {
                $scope.show = false;
            }
        });

    }
]);
app.directive('property', [() => {
    return {
        restrict: 'E',
        replace: true,
        controller: 'PropertyCtrl',
        templateUrl: '/js/composer/property.html',
        scope: {
            types: '=',
            model: '=',
            index: '='
        }
    };
}]);