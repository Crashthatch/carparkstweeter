FROM tutum/apache-php
RUN apt-get update && apt-get install -yq php5-pgsql git
RUN rm -fr /app
WORKDIR /app
RUN curl -sS https://getcomposer.org/installer | php

#Replace app with our turk-submit app.
ADD ./composer.json /app/composer.json
RUN php composer.phar install
ADD . /app/

CMD php tweet.php