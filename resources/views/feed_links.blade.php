<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
    </head>
    <body class="antialiased">
     <table>
         <tr>
             <th style="border: black 1px solid;">Source</th>
             @foreach ($platforms as $platform)
                 <th style="border: black 1px solid;">{{$platform['name']}}@if(!$platform['isActual']) (not actual)@endif</th>
             @endforeach
         </tr>
         @foreach ($feeds as $devName =>  $feed)
             <tr>
                 <td style="border: black 1px solid;">{{$devName}}@if(empty($feed['actual'])) (not actual))@endif</td>
                 @foreach ($platforms as $platform)
                     @if(isset($feed[$platform['name']]))
                         <td style="border: black 1px solid;">
                             @if(isset($feed[$platform['name']]['dynamicLink']))
                                 <a target="_blank" href="/feeds/preview/{{$feed[$platform['name']]['dynamicLink']}}">open</a><br/>
                                 <a target="_blank" href="/feeds/write/{{$feed[$platform['name']]['dynamicLink']}}">write</a><br/>
                             @endif
                             @if(isset($feed[$platform['name']]['staticLink']))
                                     <a target="_blank" href="/feeds/static/{{$feed[$platform['name']]['staticLink']}}">{{$feed[$platform['name']]['staticLinkDate']}}</a><br/>
                                 @endif
                         </td>
                     @else
                         <td style="border: black 1px solid;"></td>
                     @endif
             @endforeach
             </tr>
         @endforeach
     </table>
     @foreach ($warnings as $warning)
         <h4 style="color: deeppink;">{{$warning}}</h4><br/>
     @endforeach

     @error('message')
        <h4 style="color: red;">{{$message}}</h4>
     @enderror
    </body>
</html>
