<!DOCTYPE html>
<html lang="" class="no-js">
  <head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, minimal-ui">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<title>Patient Record</title>
	<link rel="stylesheet" href="/reports/css/main.css">
  	<script src="/reports/js/jquery-1.12.4.min.js"></script>
	  <script>
			$(document).ready(function(){
				var currentPagePixels = 0;
				var limitPagePixels = 905;

				$('table').each(function(){
					if(!currentPagePixels){
						currentPagePixels = $(this).offset().top;
					}

					var nextHalf = $(this).next('table');

					var heightImg = nextHalf.find('tr img').height();

					var nextElementHeight = $(this).next('table').height();

					if(heightImg > 0){
						//nextElementHeight = nextElementHeight + heightImg + 106;
					}

					if($(this).next('table').length && (nextHalf.offset().top + nextElementHeight - currentPagePixels) > limitPagePixels){
						$(this).addClass('page-break');
						currentPagePixels = nextHalf.offset().top;
						limitPagePixels = 1280;
					}
				})
			})
	  </script>
  </head>
  <body>
	<div class="page">
	  <header style="" class="header">
		<div class="header__main-info">
		  <div class="card">
			@if ($patientRecord['doctor']['image'] )
			<div class="card-avatar"><img src="{{$patientRecord['doctor']['image']}}" alt=""></div>
			@endif
			<div class="card-info">
			  <div class="card-info__title">{{ $patientRecord['patient']['first_name'] . ' ' . $patientRecord['patient']['last_name'] }}</div>
			  <div class="info-list">
				<div class="info-row"><span class="info-row__label">Clinical path:</span><span class="info-row__value">{{ $patientRecord['clinical_path']['name'] }}</span></div>
				<div class="info-row"><span class="info-row__label">Treatment path:</span><span class="info-row__value">{{ $patientRecord['treatment_path']['name'] }}</span></div>
				<div class="info-row"><span class="info-row__label">Date of Treatment:</span><span class="info-row__value">{{ $patientRecord['case_date'] }}</span></div>
				<div class="info-row"><span class="info-row__label">Attending Clinician:</span><span class="info-row__value">{{ $patientRecord['doctor']['first_name'] . ' ' . $patientRecord['doctor']['last_name'] }}</span></div>
			  </div>
			</div>
		</div>
		  <div class="logo">
			@if ( $patientRecord['doctor']['logo'])
			<img src="{{ $patientRecord['doctor']['logo'] }}" alt="">
			@elseif ($patientRecord['affiliate']['image'])
			<img src="{{ $patientRecord['affiliate']['image'] }}" alt="">
			@endif
		</div>
		</div>
		<div class="contacts-list">
			@if (@$patientRecord['doctor_public_contacts']['mobile_phone'])
		  <div class="contact__item"><span class="contact__icon"><img src="/reports/img/general/mobile.svg" alt=""></span><span class="contact__text">{{ $patientRecord['doctor']['mobile_phone'] }}</span></div>
			@endif

			@if (@$patientRecord['doctor_public_contacts']['primary_phone'])
		  <div class="contact__item"><span class="contact__icon"><img src="/reports/img/general/phone.svg" alt=""></span><span class="contact__text">{{ $patientRecord['doctor']['primary_phone'] }}</span></div>
			@endif

			@if (@$patientRecord['doctor_public_contacts']['email'])
		  <div class="contact__item"><span class="contact__icon"><img src="/reports/img/general/envelope.svg" alt=""></span><span class="contact__text">{{ $patientRecord['doctor']['email'] }} </span></div>
			@endif
		</div>
	  </header>
	  <main class="main-content">
		<div class="container">
		  <div class="main-content__header text-center">
			<h1 class="chapter">Patient Record</h1>
			<h2 class="sub-chapter">NEXT APPOINTMENT</h2>
			<p>1 Week Follow Up: Answer by Email</p>
		  </div>
		</div>
    @php $currentSurveyId = 0; $currentQuestionId = 0; @endphp
		  @foreach ($patientRecord['patient_record_list'] as $item)

			  @foreach($item['questions'] as $question)



				  @foreach($question['subquestions'] as $subquestion)
		<table class="table subquest">

			<tbody>

      @if ($currentSurveyId != $item['survey']['id'] )

        @php $currentSurveyId = $item['survey']['id']; @endphp

				<tr class="question-head">
					<td>{{ $item['survey']['name'] }}</td>
					<td class="th-w-sm">Your Data</td>
					@if(!isset($patientRecord['params']['hide_benchmark_datas']))
					<td class="th-w-sm">Benchmark
						<div class="th-light">(Optional)</div>
					</td>
					@endif
				</tr>

      @endif

			<tr class="subquestion-head">

			@php
			$showAnswer = function($answer) {
				if(isset($answer->name)) {
					return $answer->name;
				}

				if(is_array($answer)) {
					if (count($answer) == 2 && is_int($answer[1])) {
						return $answer[0] . ' (' . $answer[1] . ')';
					} else {
						return implode("; ", $answer);
					}
				}

				return $answer;
			}
			@endphp
			  <td class="th-lg-text">
				  @if($currentQuestionId != $question['id'] && $question['section_title'])
					  @php $currentQuestionId = $question['id']; @endphp
					  <div class="section_header"><span>{{$question['section_title']}}</span></div>
				  @endif
				  {{ $subquestion['text'] }}
			  </td>
			  <td class="th-w-sm">{{ implode(", ", array_map($showAnswer, $subquestion['selected_answer']))." ".$subquestion['identifier'] }}</td>
				@if(!isset($patientRecord['params']['hide_benchmark_datas']))
			  <td class="th-w-sm">
				  <div class="row">
					  <div class="col-7">
						  {{ empty($patientRecord['params']['hide_benchmark_datas']) ? implode(", ", array_map($showAnswer, $subquestion['benchmark_answer']))." ".$subquestion['identifier'] : "" }}
					  </div>
					  <div class="col-5 text-right">
						  {{ empty($patientRecord['params']['hide_benchmark_datas']) ? $subquestion['benchmark_sample_size'] : "" }}
					  </div>
				  </div>
			  </td>
				@endif
			</tr>
			  <tr>
				  <td colspan="{{ empty($patientRecord['params']['hide_benchmark_datas']) ? "3" : "2" }}" style="background: none; border: none;">
				  <div class="text-w">
					  <p>{{ $question['patient_description'] }}</p>
				  </div>
				  <div class="img-group">
					  @foreach ($question['files'] as $file)
						  @if (!(\App\Services\NetHelper::remoteFileExists($file['filename'])))
							  @continue
						  @endif
						  @php
							  	list($width, $height) = getimagesize($file['filename']);
						  @endphp
						  <div class="img-w"><img height="
								@if($height > 1280)
									  1280px
								@endif
									  " src="{{ $file['filename'] }}" alt="" class="img"></div>
					  @endforeach
				  </div>
				  </td>
			  </tr>
			@if (($subquestion['description']) || (count($subquestion['files'])))
			<tr>
				<td colspan="{{ empty($patientRecord['params']['hide_benchmark_datas']) ? "3" : "2" }}" style="background: none; border: none;">
					<div class="">
					  <div class="text-w">
						  <p>{!! str_replace("\t","&emsp;",str_replace("\n","<br/>",$subquestion['description'])) !!}</p>
					  </div>
					  <div class="img-group">
						@foreach ($subquestion['files'] as $file)
							@php
							  list($width, $height) = getimagesize($file['filename']);
						  	@endphp
						<div class="img-w"><img
									height="@if($height > 1280)
											1280px
											@endif"
									src="{{ $file['filename'] }}" alt="" class="img"></div>
						@endforeach
					  </div>
					</div>
				</td>
			</tr>
			@endif

<tr class="page-number">
				<td colspan="3"></td>
			</tr>

			</tbody>
		</table>

				  @endforeach
			  @endforeach
		  @endforeach

	  </main>
	</div>
	<!-- #wrapper-->
  </body>
</html>
