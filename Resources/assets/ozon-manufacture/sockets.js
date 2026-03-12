/*
 *  Copyright 2026.  Baks.dev <admin@baks.dev>
 *  
 *  Permission is hereby granted, free of charge, to any person obtaining a copy
 *  of this software and associated documentation files (the "Software"), to deal
 *  in the Software without restriction, including without limitation the rights
 *  to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 *  copies of the Software, and to permit persons to whom the Software is furnished
 *  to do so, subject to the following conditions:
 *  
 *  The above copyright notice and this permission notice shall be included in all
 *  copies or substantial portions of the Software.
 *  
 *  THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 *  IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 *  FITNESS FOR A PARTICULAR PURPOSE AND NON INFRINGEMENT. IN NO EVENT SHALL THE
 *  AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 *  LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 *  OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 *  THE SOFTWARE.
 *
 */

/** Сокеты */

let eYlcMFxJQ = 100;

setTimeout(function SpgGHaQzun()
{
    if(typeof centrifuge !== "object")
    {
        if(eYlcMFxJQ > 1000)
        { return; }

        eYlcMFxJQ = eYlcMFxJQ * 2;
        return setTimeout(SpgGHaQzun, eYlcMFxJQ);
    }

    if(window.manufacture_part_event !== 'null')
    {
        const ozon_manufacture_part_channel = centrifuge.newSubscription(window.manufacture_part_event);

        /** Изменение полей в производственной партии */
        ozon_manufacture_part_channel.on("publication", function(ctx)
        {
            /**
             * Последний добавленный продукт в производственной партии
             * @note ctx.data.product - блок с html
             * */
            const lastProduct = document.getElementById('product-' + window.manufacture_part_event);

            lastProduct.innerHTML = ctx.data.product;

            /** Всего продукции в производственной партии */
            let total = parseInt(document.getElementById('total-' + window.manufacture_part_event).textContent);
            document.getElementById('total-' + window.manufacture_part_event).textContent = total + ctx.data.total;

        }).subscribe();
    }


    if(window.current_profile)
    {

        const remove_channel = centrifuge.newSubscription('remove');

        /** Удаляем у всех продукт из списка */
        remove_channel.on("publication", function(ctx)
        {
            let identifier = document.getElementById(ctx.data.identifier);

            //const addButton = identifier.querySelector('[data-post-class="add-one-to-collection"]');

            if(ctx.data.profile === window.current_profile)
            {
                return;
            }

            if(identifier)
            {
                identifier.remove();
            }

        }).subscribe();
    }

}, 100);