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


/** Обработчики кнопок выбора товаров */
let select_all_to_production = document.querySelector("#select-all-to-production");
let add_all_to_production = document.querySelector("#add-all-to-production");

/** Выбор из списка ответов */
select_all_to_production?.addEventListener("click", function()
{

    // Выбрать все
    select_all_to_production.classList.toggle("selected");

    if(select_all_to_production.classList.contains("selected"))
    {
        select_all_to_production.innerText = "Снять выбор";
        select_all_to_production.classList.remove("btn-outline-primary");
        select_all_to_production.classList.add("btn-primary");
    } else
    {
        select_all_to_production.innerText = "Выбрать все";
        select_all_to_production.classList.add("btn-outline-primary");
        select_all_to_production.classList.remove("btn-primary");
    }


    const products = document.querySelectorAll(".add-all-to-production");

    // Выбрать все НЕ disabled (т.е. те, которые не на производстве)
    products.forEach(checkbox =>
    {
        if(!checkbox.disabled)
        { checkbox.checked = select_all_to_production.classList.contains("selected");}
    });

    const checkboxes = document.querySelectorAll(".add-all-to-production");
    const atLeastOneChecked = Array.from(checkboxes).some(cb => cb.checked);

    if(atLeastOneChecked)
    {
        add_all_to_production.classList.remove("d-none");
    } else
    {
        add_all_to_production.classList.add("d-none");
    }

});

let checkboxs_all_to_production = document.querySelectorAll(".add-all-to-production");

/** Скрыть или показать кнопку "Добавить выбранные" */
for(checkbox_all_to_production of checkboxs_all_to_production)
{

    checkbox_all_to_production?.addEventListener("click", function()
    {

        const checkboxes = document.querySelectorAll(".add-all-to-production");
        const atLeastOneChecked = Array.from(checkboxes).some(cb => cb.checked);

        if(atLeastOneChecked)
        {
            add_all_to_production.classList.remove("d-none");
        } else
        {
            add_all_to_production.classList.add("d-none");
        }

    });
}