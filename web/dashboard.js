$(document).ready(function() {
  if (window.Chart === undefined) {
    return;
  }
    $('#adblocker-notice').hide();
    
    var COLORS = ['rgba(65, 168, 95, 0.5)', 'rgba(0, 168, 133, 0.5)', 'rgba(61, 142, 185, 0.5)', 'rgba(41, 105, 176, 0.5)', 'rgba(85, 57, 130, 0.5)', 'rgba(40, 50, 78, 0.5)', 'rgba(250, 197, 28, 0.5)', 'rgba(243, 121, 52, 0.5)', 'rgba(209, 72, 65, 0.5)', 'rgba(184, 49, 47, 0.5)', 'rgba(247, 218, 100, 0.5)', 'rgb(235, 107, 86, 0.5)'];
    
    var COLORS = ['rgb(255, 99, 132)', 'rgb(255, 159, 64)', 'rgb(255, 205, 86)', 'rgb(75, 192, 192)', 'rgb(54, 162, 235)', 'rgb(153, 102, 255)', 'rgb(201, 203, 207)'];
    
    var default_options = {
      responsive: true,
      title: {
        display: false
      },
      tooltips: {
        mode: 'index',
        intersect: false,
      },
      legend: {
        display: true,
        position: 'bottom'
      },
      hover: {
        mode: 'nearest',
        intersect: true
      },
      scales: {
        xAxes: [{
          display: true,
          scaleLabel: {
            display: true
          }
        }],
        yAxes: [{
          display: true,
          scaleLabel: {
            display: false
          }
        }]
      }
    };

    var showAlert = function(txt, cls) {
      var el = $('#alert-notice');
      cls = cls || 'warning';
      var _alert = $('<div class="alert alert-' + cls + '"><button type="button" class="close" data-dismiss="alert">&times;</button><strong>¡Aviso!</strong> <span>' + txt + '</span></div>');
      el.append(_alert);
    }
    
    
    // Render all the of charts for this view.
    renderLastWeekChart('chart-0-container', 7, 'line', 'newUsers', 'Nuevos alumnos');
    renderLastWeekChart('chart-0b-container', 7, 'line', 'activeUsers', 'Alumnos activos');
    renderLastMonthChart('chart-1-container', 30, 'line', 'newUsers', '%a', moment(), 1, 0);
    m = moment.utc([moment().year(), 11, 31]);
    m.set({hour:0,minute:0,second:0,millisecond:0})
    renderLastMonthChart('chart-2-container', 12, 'bar', 'newUsers', '%m', m, 0, 1);
    renderPercentChart('chart-3-container', 'doughnut', 'payedUsers', ['Desconocido', 'Alumnos con plan activo', 'Alumnos con plan caducado']);
    renderPercentChart('chart-4-container', 'doughnut', 'roleUsers', ['Otros', 'AlumnoK', 'Alumno', 'Demo']);
    renderPercentChart('chart-5-container', 'doughnut', 'createdByUsers', null);

    function renderPercentChart(id, type, dimension, labels) {

      $.ajax({
          url: "/bolt/extensions/authdashboard/usersQuery",
          headers: {
           'Content-Type':'application/json'
          },
          type: "GET",
          timeout: 5000,
          dataType: 'json',
          data: {
            dimension: dimension
          },
          success: function(data, status) {
            if (!labels) {
              labels = data.labels;
              data = data.data;
            }  
            var config = {
        			type: type,
        			data: {
        				datasets: [{
        					data: data,
        					backgroundColor: COLORS
        				}],
        				labels: labels
        			},
        			options: {
        				responsive: true,
        				legend: {
        					position: 'bottom',
        				},
        				title: {
        					display: false
        				},
                animation: {
        					animateScale: true,
        					animateRotate: true
        				}
        			}
        		};

            new Chart(makeCanvas(id), config);
            updateTitle(id.replace('chart-', 'legend-'), data);
            
          },
          error: function(){
            showAlert('No se han podido recuperar los datos');
          }
      });
    }


    
    /**
    * Draw the a chart.js line chart with data from the specified view that
    * overlays session data for the current week over session data for the
    * previous week.
    */
    function renderLastWeekChart(id, days, type, dimension, tit) {

      // Adjust `now` to experiment with different days, for testing only...
      var now = moment();

      var thisRange = {
        'start-date': moment(now).subtract(days-1, 'day').format('YYYY-MM-DD'),
        'end-date': moment(now).format('YYYY-MM-DD')
      };

      var lastRange = {
        'start-date': moment(now).subtract(days*2-1, 'day').format('YYYY-MM-DD'),
        'end-date': moment(now).subtract(days-1, 'day').subtract(1, 'day')
        .format('YYYY-MM-DD')
      };
      
      $.ajax({
          url: "/bolt/extensions/authdashboard/usersQuery",
          headers: {
           'Content-Type':'application/json'
          },
          type: "GET",
          timeout: 5000,
          dataType: 'json',
          data: {
            current: thisRange,
            compare: lastRange,
            dimension: dimension,
            unit: '%a'
          },
          success: function(data, status) {
            var labels = [];
            for (var i = 0; i<days; i++) {
              labels.push(moment(now).subtract(days-i-1, 'day').format('ddd (Do)'));
            }

            var data = {
              labels : labels,
              datasets : [
              {
                label: tit + ' periodo actual',
                backgroundColor : "rgba(101,120,225,0.2)",
                borderColor : "rgba(101,120,225,0.4)",
                data : data[0],
                fill: true
              },
              {
                label: tit + ' periodo anterior',
                backgroundColor : "rgba(121,222,130,0.2)",
                borderColor : "rgba(121,222,130,0.4)",
                data : data[1],
                fill: true
              }
              ]
            };
            var config = {
              type: 'line',
              data: data,
              options: default_options
            }

            new Chart(makeCanvas(id), config);
            //generateLegend('legend-0-container', data.datasets);
          },
          error: function(){
            showAlert('No se han podido recuperar los datos');
          }
      });
      
    }
    
    var rand = function(min, max) {
    			var seed = Date.now();
    			min = min === undefined ? 0 : min;
    			max = max === undefined ? 1 : max;
    			seed = (seed * 9301 + 49297) % 233280;
    			return min + (seed / 233280) * (max - min);
    		}
        
    var randomScalingFactor = function(factor=1) {
    		return Math.round(rand(10, 10*factor));
    };

    
    function randomize(data, factor=1) {
			return data.map(function() {
				return randomScalingFactor(factor);
			});
    }
    
    /**
    * Draw the a chart.js line chart with data from the specified view that
    * overlays session data for the current week over session data for the
    * previous week.
    */
    function renderLastMonthChart(id, days, type, dimension, unit, now, adjm, adjd) {
      // Adjust `now` to experiment with different days, for testing only...
      var aux = 'day';
      if (unit == '%m') {
        aux = 'months';
      }
      var thisRange = {
        'start-date': moment(now).subtract(days-adjm, aux).add(adjd, 'day').format('YYYY-MM-DD'),
        'end-date': moment(now).format('YYYY-MM-DD')
      };

      var lastRange = {
        'start-date': moment(now).subtract(days*2-adjm, aux).add(adjd, 'day').format('YYYY-MM-DD'),
        'end-date': moment(now).subtract(days-adjm, aux).format('YYYY-MM-DD')
      };
      
      $.ajax({
          url: "/bolt/extensions/authdashboard/usersQuery",
          headers: {
           'Content-Type':'application/json'
          },
          type: "GET",
          timeout: 5000,
          dataType: 'json',
          data: {
            current: thisRange,
            compare: lastRange,
            dimension: dimension,
            unit: unit
          },
          success: function(data, status) {
            var labels = [];
            for (var i = 0; i<days; i++) {
              labels.push(i+1);
            }

            var dataset = {
              labels : labels,
              datasets : [
              {
                label: 'Nuevos alumnos periodo actual',
                backgroundColor : "rgba(101,120,225,0.2)",
                borderColor : "rgba(101,120,225,0.4)",
                pointColor : "rgba(101,120,225,0.6)",
                data : data[0],
                fill: true
              },
              {
                label: 'Nuevos alumnos periodo anterior',
                //backgroundColor : "rgba(101,120,225,0.5)",
                //borderColor : "rgba(101,120,225,0.8)",
                //pointColor : "rgba(101,120,225,1)",
                //pointStrokeColor : "rgba(0,0,0,0)",
                backgroundColor : "rgba(121,222,130,0.2)",
                borderColor : "rgba(121,222,130,0.4)",
                //pointColor : "rgba(121,222,130,0.6)",
                pointStrokeColor : "#ccc",
                data : data[1],
                fill: true
              }
              ]
            };
            
            var config = {
              type: 'line',
              data: dataset,
              options: default_options
            }

            new Chart(makeCanvas(id), config);
            //generateLegend('legend-0-container', data.datasets);
          },
          error: function(){
            showAlert('No se han podido recuperar los datos');
          }
      });
      
    }
    
    /**
    * Create a new canvas inside the specified element. Set it to be the width
    * and height of its container.
    * @param {string} id The id attribute of the element to host the canvas.
    * @return {RenderingContext} The 2D canvas context.
    */
    function makeCanvas(id) {
      var container = document.getElementById(id);
      var canvas = document.createElement('canvas');
      var ctx = canvas.getContext('2d');

      container.innerHTML = '';
      canvas.width = container.offsetWidth;
      canvas.height = container.offsetHeight;
      container.appendChild(canvas);

      return ctx;
    }
    
    /**
    * Create a visual legend inside the specified element based off of a
    * Chart.js dataset.
    * @param {string} id The id attribute of the element to host the legend.
    * @param {Array.<Object>} items A list of labels and colors for the legend.
    */
    function updateTitle(id, items) {
      var legend = document.getElementById(id);
      legend.innerHTML = '(' + items.reduce(function(total, num) {
        return total + num;
      }) + ')';
    }
      
})