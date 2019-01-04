'use strict';

app.controller('NOSQLCtrl', ['$scope', '$httpSrv', '$msgSrv', '$timeout',
    ($scope, $httpSrv, $msgSrv, $timeout) => {
        $scope.types = [];
        $scope.domains = [];
        $scope.domain = null;
        $scope.collections = [];
        $scope.collection = null;
        $scope.indexes = [];
        $scope.index = [];
        $scope.waiting = false;
        $scope.loading = false;
        $scope.hasMessage = false;
        $scope.message = '';

        function loadTypes() {
            $httpSrv.$get('/NOSQL/Api/__admin/types')
                .then((response) => {
                    if(response.data.success) {
                        $scope.types = response.data.data;
                    }
                }).finally(() => $msgSrv.send('nosql.types.loaded'));
        }

        function loadDomains() {
            cleanContext();
            $httpSrv.$get('/NOSQL/Api/__admin/domains')
                .then((response) => {
                    if(response.data.success) {
                        $scope.domains = response.data.data;
                    }
                }).finally(() => {
                    $msgSrv.send('nosql.domains.loaded');
                    $msgSrv.send('load.ui');
            });
        }

        function loadUI() {
            $timeout(() => {
                try {
                    $('[data-toggle=tooltip]').tooltip();
                }catch(err) {
                    loadUI();
                }
            }, 250);
        }

        function cleanContext() {
            $scope.collection = null;
            $scope.collections = [];
            $scope.index = [];
            $scope.indexes = [];
            $scope.domain = null;
        }

        $scope.loadCollections = (domain) => {
            cleanContext();
            $scope.loading = true;
            $scope.domain = domain;
            if(localStorage) {
                $scope.collections = JSON.parse(localStorage.getItem(domain.toLowerCase() + '.collections')) || [];
            }
            if(!$scope.collections.length) {
                $httpSrv.$get('/NOSQL/Api/__admin/' + domain + '/collections')
                    .then((response) => {
                        if(response.data.success && response.data.data.length) {
                            $scope.collections = response.data.data;
                        }
                    }).finally(() => {
                    $msgSrv.send('nosql.collections.loaded');
                    $scope.storeCollections(true);
                    $scope.loading = false;
                    $msgSrv.send('load.ui');
                });
            } else {
                $scope.loading = false;
            }
        };

        $scope.clearStorage = () => {
            $scope.waiting = true;
            if(localStorage) {
                localStorage.clear();
                location.reload(true);
            }
        };

        $scope.addNewProperty = () => {
            let _ts = (new Date()).getTime();
            $scope.collection.properties.push({
                id: _ts,
                name: 'property_' + _ts,
                type: 'string'
            });
        };

        $scope.createCollection = () => {
            let _ts = (new Date()).getTime();
            $scope.collections.push({
                id: _ts,
                name: 'new_' + _ts,
                properties: []
            });
            for(let i in $scope.collections) {
                let collection = $scope.collections[i];
                if(collection.id === _ts) {
                    $scope.editCollection(collection);
                    break;
                }
            }
        };

        $scope.removeCollection = (index) => {
            $scope.collection = null;
            $scope.collections.splice(index, 1);
            $scope.storeCollections(true);
        };

        $scope.editCollection = (collection) => {
            let change = true;
            if($scope.collection) {
                change = $scope.collection.id !== collection.id;
            }
            $scope.collection = null;
            if(change) {
                $scope.collection = collection;
            }
            $msgSrv.send('load.ui');
        };

        $scope.storeCollections = (hideAlert) => {
            hideAlert = hideAlert || false;
            if(localStorage) {
                localStorage.setItem($scope.domain.toLowerCase() + '.collections', JSON.stringify($scope.collections));
                if(!hideAlert) {
                    bootbox.alert(translations['collection_stored_successfull']);
                }
            } else if(!hideAlert) {
                bootbox.alert(translations['collection_stored_fail']);
            }
        };

        $scope.saveAndCreate = () => {
            $scope.waiting = true;
            $httpSrv.$put('/NOSQL/Api/__admin/' + $scope.domain + '/collections', $scope.collections)
                .then((response) => {
                    if(response.data.success) {
                        bootbox.alert(translations['domain_generated_success']);
                    } else {
                        bootbox.alert(translations['domain_generated_fail']);
                    }
                }, () => bootbox.alert(translations['domain_generated_fail']))
                .finally(() => {
                    $msgSrv.send('nosql.collections.saved');
                    $scope.waiting = false;
                    $msgSrv.send('load.ui');
                });
        };

        $scope.syncCollections = () => {
            $scope.waiting = true;
            $httpSrv.$post('/NOSQL/Api/__admin/' + $scope.domain + '/sync')
                .then((response) => {
                    if(response.data.success) {
                        bootbox.alert(translations['domain_sync_success']);
                    } else {
                        bootbox.alert(translations['domain_sync_fail']);
                    }
                }, () => bootbox.alert(translations['domain_sync_fail']))
                .finally(() => {
                    $msgSrv.send('nosql.collections.sync');
                    $scope.waiting = false;
                    $msgSrv.send('load.ui');
                });
        };

        // Initialization
        function init() {
            loadTypes();
            loadDomains();
            loadUI();
        }

        $timeout(init, 250);

        // Listeners
        $scope.$on('property.remove', (ev, index) => {
            $scope.collection.properties.splice(index, 1);
        });

        $scope.$on('show.message', (ev, message) => {
            $scope.message = message;
            $scope.hasMessage = true;
            $timeout(() => {
                $scope.hasMessage = false;
            }, 3000);
        });

        $scope.$on('load.ui', loadUI);
    }
]);